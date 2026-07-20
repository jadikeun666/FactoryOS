<?php

namespace App\Providers;

use App\Events\ProductionLogSaved;
use App\Events\ScheduleCreated;
use App\Listeners\RecalculateOeeListener;
use App\Listeners\TriggerMrpRunListener;
use App\Models\ProductionLog;
use App\Observers\ProductionLogObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ProductionLog::observe(ProductionLogObserver::class);

        Event::listen(
            ProductionLogSaved::class,
            RecalculateOeeListener::class,
        );

        Event::listen(
            ScheduleCreated::class,
            TriggerMrpRunListener::class,
        );
    }
}