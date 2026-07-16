<?php

namespace App\Jobs;

use App\Events\OeeUpdated;
use App\Models\ProductionLog;
use App\Services\OEE\OeeCalculatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @see docs/architecture.md § Jobs (Queued)
 */
class RecalculateOeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10; // detik

    public function __construct(private readonly ProductionLog $log)
    {
    }

    public function handle(OeeCalculatorService $calculator): void
    {
        $snapshot = $calculator->compute($this->log);

        broadcast(new OeeUpdated($snapshot))->toOthers();
    }

    public function failed(Throwable $e): void
    {
        Log::error('RecalculateOeeJob failed', [
            'log_id' => $this->log->id,
            'error'  => $e->getMessage(),
        ]);
    }
}