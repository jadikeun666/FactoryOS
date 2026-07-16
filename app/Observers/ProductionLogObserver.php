<?php

namespace App\Observers;

use App\Events\ProductionLogSaved;
use App\Models\ProductionLog;

/**
 * @see docs/architecture.md § Observers
 */
class ProductionLogObserver
{
    public function created(ProductionLog $log): void
    {
        ProductionLogSaved::dispatch($log);
    }

    public function updated(ProductionLog $log): void
    {
        // Validated log tidak bisa trigger recalc (immutability rule)
        if ($log->is_validated) {
            return;
        }

        ProductionLogSaved::dispatch($log);
    }
}