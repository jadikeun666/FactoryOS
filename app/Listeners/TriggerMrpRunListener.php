<?php

namespace App\Listeners;

use App\Events\ScheduleCreated;
use App\Jobs\RunMrpJob;

/**
 * TriggerMrpRunListener — memicu RunMrpJob saat Schedule baru dibuat.
 * @see docs/architecture.md § Events & Listeners
 */
class TriggerMrpRunListener
{
    public function handle(ScheduleCreated $event): void
    {
        RunMrpJob::dispatch($event->schedule);
    }
}