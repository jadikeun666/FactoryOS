<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ProductionLogController;
use App\Http\Controllers\DowntimeController;
use Inertia\Inertia;
use App\Http\Controllers\OeeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {
    Route::resource('work-orders', WorkOrderController::class);
    Route::patch('work-orders/{workOrder}/status', [WorkOrderController::class, 'updateStatus'])
        ->name('work-orders.update-status');
    Route::post('work-orders/{workOrder}/regenerate-operations', [WorkOrderController::class, 'regenerateOperations'])
        ->name('work-orders.regenerate-operations');
});

Route::middleware('auth')->group(function () {
    Route::post('/schedules/apply', [ScheduleController::class, 'apply'])
        ->name('schedules.apply');
});

Route::middleware('auth')->group(function () {
    Route::resource('production-logs', ProductionLogController::class);
    Route::patch('production-logs/{productionLog}/validate', [ProductionLogController::class, 'validateAction'])
        ->name('production-logs.validate');

    Route::post('production-logs/{productionLog}/downtime-events', [DowntimeController::class, 'store'])
        ->name('production-logs.downtime-events.store');
    Route::patch('production-logs/{productionLog}/downtime-events/{downtimeEvent}', [DowntimeController::class, 'update'])
        ->name('production-logs.downtime-events.update');
    Route::delete('production-logs/{productionLog}/downtime-events/{downtimeEvent}', [DowntimeController::class, 'destroy'])
        ->name('production-logs.downtime-events.destroy');
    
    Route::get('/dev/oee-gauge-test', fn () => Inertia::render('Dev/OeeGaugeTest'));
});

    Route::middleware('auth')->group(function () {
        Route::get('/oee/dashboard', [OeeController::class, 'dashboard'])->name('oee.dashboard');
    });


    Route::middleware('auth')->group(function () {
    Route::post('/schedules/run', [ScheduleController::class, 'run'])
        ->name('schedules.run');
    Route::post('/schedules/compare-all', [ScheduleController::class, 'compareAll'])
        ->name('schedules.compare-all');
    Route::get('/schedules/{schedule}', function (\App\Models\Schedule $schedule, \App\Services\Scheduling\GanttBuilderService $gantt) {
        $siblingIds = \App\Models\Schedule::query()
            ->where('scheduled_from', $schedule->scheduled_from)
            ->pluck('id', 'algorithm');
        return \Inertia\Inertia::render('Schedules/Show', [
            'initialData' => $gantt->build($schedule),
            'scheduleIds' => $siblingIds,
        ]);
                })->name('schedules.show');

        Route::post('/schedules/apply', [ScheduleController::class, 'apply'])
            ->name('schedules.apply');
    });

require __DIR__.'/auth.php';
