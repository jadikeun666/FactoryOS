<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @see docs/exports.md § PDF Reports > 1. Laporan Jadwal Produksi (Engine 1)
 *
 * PENTING: job ini TIDAK memakai GanttBuilderService (final, untuk kebutuhan
 * Gantt Chart JSON di frontend) karena bentuk tabel yang dibutuhkan laporan
 * PDF berbeda (per-WO dengan status terlambat, per-mesin terurut waktu) —
 * job ini mengolah relasi Schedule langsung, sesuai contoh kode di
 * docs/exports.md § PDF Reports > 1.
 */
class GeneratePdfReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10; // detik

    public function __construct(
        private readonly Schedule $schedule,
        private readonly int $userId,
    ) {
    }

    public function handle(): void
    {
        $schedule = $this->schedule->load([
            'assignments.woOperation.workOrder.product',
            'assignments.workCenter',
        ]);

        // Baris per Work Order: status on-time/terlambat dihitung dari
        // operasi terakhir (max end_at) dibanding due_date WO tsb.
        $woRows = $schedule->assignments
            ->groupBy(fn ($assignment) => $assignment->woOperation->workOrder->id)
            ->map(function ($assignments) {
                $workOrder = $assignments->first()->woOperation->workOrder;
                $lastEnd = $assignments->max('end_at');
                $isLate = $lastEnd->greaterThan($workOrder->due_date->copy()->endOfDay());

                return [
                    'work_order' => $workOrder,
                    'last_end'   => $lastEnd,
                    'is_late'    => $isLate,
                ];
            })
            ->sortBy(fn ($row) => $row['work_order']->due_date)
            ->values();

        // Baris per operasi: diurutkan per mesin, lalu per waktu mulai
        // (docs/exports.md: "Diurutkan per mesin, per waktu mulai").
        $machineRows = $schedule->assignments
            ->sort(function ($a, $b) {
                return [$a->workCenter->name, $a->start_at->getTimestamp()]
                    <=> [$b->workCenter->name, $b->start_at->getTimestamp()];
            })
            ->values();

        $data = [
            'schedule'     => $schedule,
            'wo_rows'      => $woRows,
            'machine_rows' => $machineRows,
            'generated_at' => now(),
            'user'         => User::find($this->userId),
        ];

        $pdf = Pdf::loadView('exports.schedule_report', $data)
            ->setPaper('a4', 'landscape');

        // Nomor halaman via Canvas::page_text() (API resmi dompdf) — BUKAN
        // <script type="text/php"> di Blade, karena dompdf menonaktifkan
        // eksekusi PHP embedded secara default (isPhpEnabled = false).
        // Placeholder {PAGE_NUM}/{PAGE_COUNT} otomatis disubstitusi dompdf
        // per halaman saat render, tidak butuh isPhpEnabled sama sekali.
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 150,
            $canvas->get_height() - 30,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            null,
            8,
            [0.42, 0.45, 0.5]
        );

        $filename = "schedule_{$schedule->id}_".now()->format('Ymd_His').'.pdf';
        $path = "exports/{$filename}";

        Storage::disk('local')->put($path, $pdf->output());

        // Simpan path hasil ke Cache (BUKAN tabel DB baru — tidak ada tabel
        // "exports" di docs/database.md, dan mengubah skema DB di luar
        // scope/izin sesi ini). TTL 10 menit cukup untuk polling frontend
        // langsung setelah generate; expired setelahnya tidak masalah
        // karena file fisik tetap ada 7 hari (exports:cleanup) dan bisa
        // diakses lewat riwayat lain kalau nanti dibutuhkan.
        Cache::put(
            "export:schedule_pdf:{$schedule->id}:{$this->userId}",
            $path,
            now()->addMinutes(10)
        );
    }

    public function failed(Throwable $e): void
    {
        Log::error('GeneratePdfReportJob (schedule) failed', [
            'schedule_id' => $this->schedule->id,
            'error'       => $e->getMessage(),
        ]);
    }
}