<?php

namespace App\Events;

use App\Models\ProductionLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @see docs/architecture.md § Events & Listeners
 * Dispatch oleh ProductionLogObserver saat log dibuat, atau diupdate
 * selama belum divalidasi (is_validated = false).
 */
class ProductionLogSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ProductionLog $log)
    {
    }
}