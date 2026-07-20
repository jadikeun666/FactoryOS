<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Services\Inventory\MrpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RunMrpJob — dipicu oleh TriggerMrpRunListener saat ScheduleCreated.
 * @see docs/architecture.md § Jobs (Queued)
 * @see app/Listeners/TriggerMrpRunListener.php
 *
 * Pola identik dengan RecalculateOeeJob: tries=3, backoff 10 detik,
 * service di-inject via method handle() (bukan constructor), error
 * di-log di failed() alih-alih dibiarkan silent.
 */
class RunMrpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(private readonly Schedule $schedule)
    {
    }

    public function handle(MrpService $mrp): void
    {
        $mrp->run($this->schedule->id);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RunMrpJob failed', [
            'schedule_id' => $this->schedule->id,
            'error'       => $e->getMessage(),
        ]);
    }
}