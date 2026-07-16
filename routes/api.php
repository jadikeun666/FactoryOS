<?php

use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/schedules/{schedule}/gantt-data', [ScheduleController::class, 'ganttData'])
        ->name('schedules.gantt-data');
});