<?php

namespace App\Jobs;

use App\Services\Inventory\MrpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * CheckReorderAlertsJob — scan inventory vs ROP, buat ReorderAlert baru.
 * @see docs/inventory.md § Reorder Alert Logic
 * @see docs/architecture.md § Jobs (Queued) -- dijadwalkan dailyAt('06:00')
 *      di dokumentasi, TAPI Laravel Scheduler (cron) belum diverifikasi
 *      aktif di environment ini (lihat claude.md § Utang Teknis). Sesi ini
 *      job HANYA dibuat -- testing manual via `php artisan queue:work
 *      --once` setelah dispatch manual di tinker, BUKAN via schedule:run
 *      sungguhan. Jangan aktifkan Laravel Scheduler tanpa konfirmasi user.
 *
 * Pola identik dengan RecalculateOeeJob/RunMrpJob: tries=3, backoff 10
 * detik, service di-inject via method handle(), error di-log di failed().
 */
class CheckReorderAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function handle(MrpService $mrp): void
    {
        $created = $mrp->checkReorderAlerts();

        Log::info('CheckReorderAlertsJob selesai', [
            'alerts_created' => $created->count(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CheckReorderAlertsJob failed', [
            'error' => $e->getMessage(),
        ]);
    }
}