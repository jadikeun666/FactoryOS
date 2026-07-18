<?php

use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OeeController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/schedules/{schedule}/gantt-data', [ScheduleController::class, 'ganttData'])
        ->name('schedules.gantt-data');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/schedules/{schedule}/gantt-data', [ScheduleController::class, 'ganttData'])
        ->name('schedules.gantt-data');

    Route::get('/oee/pareto', [OeeController::class, 'pareto'])->name('oee.pareto');
    Route::get('/oee/trend', [OeeController::class, 'trend'])->name('oee.trend');
    Route::get('/oee/snapshots/{oeeSnapshot}/benchmark', [OeeController::class, 'benchmark'])->name('oee.benchmark');
});