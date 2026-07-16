<?php

namespace App\Listeners;

use App\Events\ProductionLogSaved;
use App\Jobs\RecalculateOeeJob;

/**
 * @see docs/architecture.md § Events & Listeners
 * ProductionLogSaved → RecalculateOeeListener → dispatch RecalculateOeeJob (queued)
 */
class RecalculateOeeListener
{
    public function handle(ProductionLogSaved $event): void
    {
        RecalculateOeeJob::dispatch($event->log);
    }
}