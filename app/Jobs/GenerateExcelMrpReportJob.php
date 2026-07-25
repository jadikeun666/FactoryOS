<?php

namespace App\Jobs;

use App\Models\Material;
use App\Models\MrpRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * @see docs/exports.md § Excel Reports > 1. MRP Grid (Engine 3)
 *
 * PENTING: maatwebsite/excel TIDAK dipakai (tidak ada versi stabil yang
 * kompatibel PHP 8.5.4 — lihat diskusi checkpoint sebelumnya). Job ini
 * pakai phpoffice/phpspreadsheet langsung, membangun 3 sheet manual sesuai
 * spesifikasi docs/exports.md, TANPA mengubah struktur/urutan kolom.
 *
 * PENTING: MrpService::computeRequirements() menghitung 'release_date'
 * tapi TIDAK menyimpannya ke DB (komentar di MrpService mengonfirmasi ini
 * — migration final tidak diubah). Job ini menghitung ULANG release_date
 * = period_date - lead_time_days, persis rumus yang sama yang sudah
 * dilakukan MrpService secara internal, dari data yang sudah tersimpan
 * (period_date, lead_time_days) — bukan logic bisnis baru.
 */
class GenerateExcelMrpReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly MrpRun $mrpRun,
        private readonly int $userId,
    ) {
    }

    public function handle(): void
    {
        $mrpRun = $this->mrpRun->load('requirements.material.inventory', 'requirements.material.inventoryParam');

        $materialIds = $mrpRun->requirements->pluck('material_id')->unique();
        $materials = Material::query()
            ->whereIn('id', $materialIds)
            ->with(['inventory', 'inventoryParam'])
            ->get()
            ->keyBy('id');

        $spreadsheet = new Spreadsheet();

        $this->buildSummarySheet($spreadsheet, $materials);
        $this->buildGridSheet($spreadsheet, $mrpRun, $materials);
        $this->buildPlannedOrderSheet($spreadsheet, $mrpRun, $materials);

        // Sheet aktif default dari `new Spreadsheet()` LANGSUNG dipakai
        // sebagai sheet "Ringkasan" (lihat buildSummarySheet), jadi tidak
        // ada sheet kosong sisa yang perlu dibuang di sini.
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        $filename = "mrp_run_{$mrpRun->id}_".now()->format('Ymd_His').'.xlsx';
        $path = "exports/{$filename}";
        $fullPath = Storage::disk('local')->path($path);

        // Pastikan direktori ada -- Storage::put() biasanya auto-create,
        // tapi Xlsx writer menulis langsung ke filesystem path, bukan
        // lewat Storage facade, jadi guard eksplisit di sini.
        Storage::disk('local')->makeDirectory('exports');
        $writer->save($fullPath);

        Cache::put(
            "export:mrp_excel:{$mrpRun->id}:{$this->userId}",
            $path,
            now()->addMinutes(10)
        );
    }

    /**
     * Sheet 1 — Ringkasan: Material, On Hand, On Order, EOQ, Safety Stock, ROP, Status.
     * "Status" = perbandingan qty_on_hand+qty_on_order vs ROP (sama dengan
     * kriteria reorder alert di docs/inventory.md, dihitung fresh -- BUKAN
     * baca dari tabel reorder_alerts, supaya konsisten dengan MrpGrid.vue
     * yang juga real-time, bukan menunggu job terjadwal jam 06:00).
     */
    private function buildSummarySheet(Spreadsheet $spreadsheet, $materials): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ringkasan');

        $headers = ['Material', 'SKU', 'On Hand', 'On Order', 'EOQ', 'Safety Stock', 'ROP', 'Status'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeaderRow($sheet, 'A1:H1');

        $row = 2;
        foreach ($materials as $material) {
            $onHand = (string) ($material->inventory->qty_on_hand ?? '0');
            $onOrder = (string) ($material->inventory->qty_on_order ?? '0');
            $rop = (string) ($material->inventoryParam->rop ?? '0');
            $currentQty = bcadd($onHand, $onOrder, 4);
            $status = bccomp($currentQty, $rop, 4) <= 0 ? 'Perlu Order' : 'Aman';

            $sheet->fromArray([
                $material->name,
                $material->sku,
                (float) $onHand,
                (float) $onOrder,
                (float) ($material->inventoryParam->eoq ?? 0),
                (float) ($material->inventoryParam->safety_stock ?? 0),
                (float) $rop,
                $status,
            ], null, "A{$row}");

            if ($status === 'Perlu Order') {
                $sheet->getStyle("A{$row}:H{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
            }

            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Sheet 2 — Grid per Material: rowspan per material, 5 baris
     * (GR/SR/POH/NR/POR) x kolom periode, sesuai docs/inventory.md §
     * Contoh MRP Grid dan pola yang sama dipakai MrpGrid.vue.
     */
    private function buildGridSheet(Spreadsheet $spreadsheet, MrpRun $mrpRun, $materials): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Grid per Material');

        $periods = $mrpRun->requirements->pluck('period_date')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->sort()
            ->values();

        // Header: Material | Baris | [periode 1] | [periode 2] | ...
        $sheet->setCellValue('A1', 'Material');
        $sheet->setCellValue('B1', 'Baris');
        foreach ($periods as $i => $period) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $i);
            $sheet->setCellValue("{$col}1", $period);
        }
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $periods->count());
        $this->styleHeaderRow($sheet, "A1:{$lastCol}1");

        $rowLabels = [
            'gross_requirement'     => 'GR',
            'scheduled_receipts'    => 'SR',
            'projected_on_hand'     => 'POH',
            'net_requirement'       => 'NR',
            'planned_order_release' => 'POR',
        ];

        $currentRow = 2;
        $reqsByMaterial = $mrpRun->requirements->groupBy('material_id');

        foreach ($reqsByMaterial as $materialId => $reqs) {
            $material = $materials->get($materialId);
            $reqsByPeriod = $reqs->keyBy(fn ($r) => $r->period_date->toDateString());

            $materialStartRow = $currentRow;

            foreach ($rowLabels as $field => $label) {
                $sheet->setCellValue("B{$currentRow}", $label);

                foreach ($periods as $i => $period) {
                    $req = $reqsByPeriod->get($period);
                    $value = $req ? (float) $req->{$field} : null;
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + $i);
                    $cellRef = "{$colLetter}{$currentRow}";
                    $sheet->setCellValue($cellRef, $value);

                    if ($field === 'net_requirement' && $value > 0) {
                        $sheet->getStyle($cellRef)->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF9C3');
                    }
                    if ($field === 'planned_order_release' && $value > 0) {
                        $sheet->getStyle($cellRef)->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
                    }
                }

                $currentRow++;
            }

            // Merge kolom Material sepanjang 5 baris (rowspan), sesuai
            // format yang sama dipakai MrpGrid.vue.
            $sheet->mergeCells("A{$materialStartRow}:A".($currentRow - 1));
            $sheet->setCellValue("A{$materialStartRow}", $material?->name ?? "Material #{$materialId}");
            $sheet->getStyle("A{$materialStartRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Sheet 3 — Planned Order Releases: hanya baris dengan
     * planned_order_release > 0, diurutkan by tanggal order keluar ascending.
     */
    private function buildPlannedOrderSheet(Spreadsheet $spreadsheet, MrpRun $mrpRun, $materials): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Planned Order Releases');

        $headers = ['Material', 'SKU', 'Qty Order', 'Tanggal Order Keluar', 'Lead Time (hari)', 'Est. Tiba'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeaderRow($sheet, 'A1:F1');

        $rows = $mrpRun->requirements
            ->filter(fn ($r) => bccomp((string) $r->planned_order_release, '0', 4) > 0)
            ->map(function ($req) use ($materials) {
                $material = $materials->get($req->material_id);
                $leadTimeDays = $material?->inventoryParam?->lead_time_days ?? 0;

                // Rumus SAMA PERSIS dengan MrpService::computeRequirements()
                // (release_date = period_date - lead_time_days), dihitung
                // ulang karena tidak disimpan ke DB (lihat catatan class).
                $releaseDate = $req->period_date->copy()->subDays($leadTimeDays);

                return [
                    'material'     => $material?->name ?? "Material #{$req->material_id}",
                    'sku'          => $material?->sku ?? '—',
                    'qty'          => (float) $req->planned_order_release,
                    'release_date' => $releaseDate,
                    'lead_time'    => $leadTimeDays,
                    'arrival_date' => $req->period_date,
                ];
            })
            ->sortBy('release_date')
            ->values();

        $row = 2;
        foreach ($rows as $r) {
            $sheet->fromArray([
                $r['material'],
                $r['sku'],
                $r['qty'],
                $r['release_date']->format('Y-m-d'),
                $r['lead_time'],
                $r['arrival_date']->format('Y-m-d'),
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function styleHeaderRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('F8FAFC');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');
    }

    public function failed(Throwable $e): void
    {
        Log::error('GenerateExcelMrpReportJob failed', [
            'mrp_run_id' => $this->mrpRun->id,
            'error'      => $e->getMessage(),
        ]);
    }
}