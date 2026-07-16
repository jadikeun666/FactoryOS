<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\ScheduleController;

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

require __DIR__.'/auth.php';
