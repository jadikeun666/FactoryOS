<?php

namespace App\Jobs;

use App\Models\ProductionLog;
use App\Models\User;
use App\Services\OEE\DowntimeAnalysisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @see docs/exports.md § PDF Reports > 2. Laporan OEE Harian (Engine 2)
 *
 * PENTING: OeeSnapshot HANYA menyimpan rasio (availability/performance/
 * quality/oee) — angka mentah (planned/downtime/output/good_output) ada di
 * ProductionLog. Job ini join keduanya berdasarkan kunci komposit yang sama
 * (work_center_id, log_date, shift_id), TIDAK menghitung ulang OEE apapun
 * (OeeCalculatorService final, tidak disentuh).
 */
class GeneratePdfOeeReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly Carbon $date,
        private readonly ?int $workCenterId,
        private readonly int $userId,
    ) {
    }

    /**
     * Service via method handle(), bukan constructor — konsisten dengan
     * docs/engineering-rules.md § "Services injected via method handle()
     * di jobs".
     */
    public function handle(DowntimeAnalysisService $downtimeService): void
    {
        $logs = ProductionLog::query()
            ->whereDate('log_date', $this->date->toDateString())
            ->when($this->workCenterId !== null, fn ($q) => $q->where('work_center_id', $this->workCenterId))
            ->with(['workCenter', 'shift'])
            ->orderBy('work_center_id')
            ->get();

        $snapshots = \App\Models\OeeSnapshot::query()
            ->whereDate('log_date', $this->date->toDateString())
            ->when($this->workCenterId !== null, fn ($q) => $q->where('work_center_id', $this->workCenterId))
            ->get()
            ->keyBy(fn ($s) => "{$s->work_center_id}|{$s->shift_id}");

        $rows = $logs->map(function (ProductionLog $log) use ($snapshots) {
            $snapshot = $snapshots->get("{$log->work_center_id}|{$log->shift_id}");

            return [
                'log'      => $log,
                'snapshot' => $snapshot,
                'oee_band' => $snapshot ? $this->oeeBand((string) $snapshot->oee) : null,
            ];
        });

        // Pareto downtime rentang satu hari yang sama, difilter mesin yang
        // sama — reuse penuh DowntimeAnalysisService (final, tidak diubah).
        $pareto = $downtimeService->paretoDowntime(
            $this->date->copy()->startOfDay(),
            $this->date->copy()->endOfDay(),
            $this->workCenterId
        );

        $data = [
            'date'         => $this->date,
            'work_center'  => $this->workCenterId
                ? $logs->first()?->workCenter
                : null,
            'rows'         => $rows,
            'pareto'       => $pareto,
            'generated_at' => now(),
            'user'         => User::find($this->userId),
        ];

        $pdf = Pdf::loadView('exports.oee_report', $data)
            ->setPaper('a4', 'landscape');

        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 150,
            $canvas->get_height() - 30,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            null,
            8,
            [0.42, 0.45, 0.5]
        );

        $filename = 'oee_report_'.$this->date->format('Ymd').'_'.now()->format('His').'.pdf';
        $path = "exports/{$filename}";

        Storage::disk('local')->put($path, $pdf->output());

        Cache::put(
            "export:oee_pdf:{$this->date->toDateString()}:".($this->workCenterId ?? 'all').":{$this->userId}",
            $path,
            now()->addMinutes(10)
        );
    }

    /**
     * Band warna sesuai docs/exports.md § PDF Reports > 2:
     * OEE < 60% → merah, 60–85% → kuning, ≥ 85% → hijau.
     * bccomp dipakai konsisten dengan aturan bcmath project meski ini murni
     * kategorisasi tampilan (nilai OEE sendiri sudah final dari OeeSnapshot).
     */
    private function oeeBand(string $oee): string
    {
        if (bccomp($oee, '0.850000', 6) >= 0) {
            return 'good';
        }

        if (bccomp($oee, '0.600000', 6) >= 0) {
            return 'warn';
        }

        return 'bad';
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneratePdfOeeReportJob failed', [
            'date'           => $this->date->toDateString(),
            'work_center_id' => $this->workCenterId,
            'error'          => $e->getMessage(),
        ]);
    }
}