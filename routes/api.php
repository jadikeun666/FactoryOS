<?php

use App\Http\Controllers\OeeController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/schedules/{schedule}/gantt-data', [ScheduleController::class, 'ganttData'])
        ->name('schedules.gantt-data');

    Route::get('/oee/pareto', [OeeController::class, 'pareto'])->name('oee.pareto');
    Route::get('/oee/trend', [OeeController::class, 'trend'])->name('oee.trend');
    Route::get('/oee/snapshots/{oeeSnapshot}/benchmark', [OeeController::class, 'benchmark'])->name('oee.benchmark');
    Route::get('/oee/work-centers/{workCenter}/latest-snapshot', [OeeController::class, 'latestSnapshotWithBenchmark'])
        ->name('oee.latest-snapshot');
});