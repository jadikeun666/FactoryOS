<?php

namespace App\Jobs;

use App\Models\OeeSnapshot;
use App\Models\ProductionLog;
use App\Models\User;
use App\Models\WorkCenter;
use App\Services\OEE\DowntimeAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * @see docs/exports.md § Excel Reports > 2. OEE Trend Bulanan (Engine 2)
 *
 * Sheet 1 (Data OEE) query OeeSnapshot LANGSUNG (satu baris per snapshot
 * per shift), BUKAN OeeCalculatorService::trendData() -- trendData()
 * mengagregasi rata-rata harian lintas shift, tidak sesuai spesifikasi
 * "satu baris per snapshot" yang butuh kolom Shift eksplisit.
 * Sheet 3 (Pareto) reuse penuh DowntimeAnalysisService (final, tidak diubah).
 */
class GenerateExcelOeeTrendReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly int $userId,
    ) {
    }

    /**
     * Service via method handle(), bukan constructor -- konsisten dengan
     * docs/engineering-rules.md.
     */
    public function handle(DowntimeAnalysisService $downtimeService): void
    {
        $snapshots = OeeSnapshot::query()
            ->whereDate('log_date', '>=', $this->from->toDateString())
            ->whereDate('log_date', '<=', $this->to->toDateString())
            ->with(['workCenter', 'shift'])
            ->orderBy('log_date')
            ->get();

        $spreadsheet = new Spreadsheet();

        $this->buildDataSheet($spreadsheet, $snapshots);
        $this->buildSummarySheet($spreadsheet, $snapshots);
        $this->buildParetoSheet($spreadsheet, $downtimeService);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        $filename = 'oee_trend_'.$this->from->format('Ym').'_'.now()->format('His').'.xlsx';
        $path = "exports/{$filename}";
        $fullPath = Storage::disk('local')->path($path);

        Storage::disk('local')->makeDirectory('exports');
        $writer->save($fullPath);

        Cache::put(
            "export:oee_trend_excel:{$this->from->format('Y-m')}:{$this->userId}",
            $path,
            now()->addMinutes(10)
        );
    }

    /**
     * Sheet 1 — Data OEE: satu baris per snapshot.
     * Kolom: Tanggal, Mesin, Shift, Availability, Performance, Quality, OEE.
     */
    private function buildDataSheet(Spreadsheet $spreadsheet, $snapshots): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data OEE');

        $headers = ['Tanggal', 'Mesin', 'Shift', 'Availability', 'Performance', 'Quality', 'OEE'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeaderRow($sheet, 'A1:G1');

        $row = 2;
        foreach ($snapshots as $snapshot) {
            $sheet->fromArray([
                $snapshot->log_date->format('Y-m-d'),
                $snapshot->workCenter->name,
                $snapshot->shift->name,
                (float) $snapshot->availability,
                (float) $snapshot->performance,
                (float) $snapshot->quality,
                (float) $snapshot->oee,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Sheet 2 — Ringkasan per Mesin: rata-rata sebulan + total downtime jam.
     * Rata-rata dihitung fresh dengan bcmath (murni agregasi tampilan,
     * bukan formula OEE baru -- nilai per snapshot sudah final dari
     * OeeCalculatorService).
     */
    private function buildSummarySheet(Spreadsheet $spreadsheet, $snapshots): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Ringkasan per Mesin');

        $headers = ['Mesin', 'Avg OEE', 'Avg Availability', 'Avg Performance', 'Avg Quality', 'Total Downtime (jam)'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeaderRow($sheet, 'A1:F1');

        $byWorkCenter = $snapshots->groupBy('work_center_id');

        $row = 2;
        foreach ($byWorkCenter as $workCenterId => $group) {
            $workCenter = $group->first()->workCenter;

            $totalDowntimeMinutes = ProductionLog::query()
                ->where('work_center_id', $workCenterId)
                ->whereDate('log_date', '>=', $this->from->toDateString())
                ->whereDate('log_date', '<=', $this->to->toDateString())
                ->sum('downtime_minutes');

            $sheet->fromArray([
                $workCenter->name,
                (float) $this->averageMetric($group->pluck('oee')),
                (float) $this->averageMetric($group->pluck('availability')),
                (float) $this->averageMetric($group->pluck('performance')),
                (float) $this->averageMetric($group->pluck('quality')),
                round(((float) $totalDowntimeMinutes) / 60, 2),
            ], null, "A{$row}");

            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Sheet 3 — Pareto Downtime: reuse penuh DowntimeAnalysisService
     * (final, tidak diubah), rentang sebulan penuh, semua mesin.
     */
    private function buildParetoSheet(Spreadsheet $spreadsheet, DowntimeAnalysisService $downtimeService): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Pareto Downtime');

        $headers = ['Kategori', 'Total Menit', 'Persentase', 'Kumulatif'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeaderRow($sheet, 'A1:D1');

        $pareto = $downtimeService->paretoDowntime($this->from, $this->to, null);

        $row = 2;
        foreach ($pareto as $p) {
            $sheet->fromArray([
                ucfirst($p['category']),
                (float) $p['total_minutes'],
                (float) $p['percentage'],
                (float) $p['cumulative'],
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Rata-rata bcmath, pola identik OeeCalculatorService::averageMetric()
     * -- ditulis ulang lokal karena scope-nya murni agregasi tampilan
     * laporan, bukan bagian dari kalkulasi formula OEE itu sendiri.
     */
    private function averageMetric($values): string
    {
        $sum = '0';
        $count = 0;

        foreach ($values as $value) {
            $sum = bcadd($sum, (string) $value, 12);
            $count++;
        }

        if ($count === 0) {
            return '0';
        }

        return bcdiv($sum, (string) $count, 6);
    }

    private function styleHeaderRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('F8FAFC');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');
    }

    public function failed(Throwable $e): void
    {
        Log::error('GenerateExcelOeeTrendReportJob failed', [
            'from'  => $this->from->toDateString(),
            'to'    => $this->to->toDateString(),
            'error' => $e->getMessage(),
        ]);
    }
}