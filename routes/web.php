<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ProductionLogController;
use App\Http\Controllers\DowntimeController;
use App\Http\Controllers\OeeController;
use App\Http\Controllers\MrpController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\WorkCenterController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;






Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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
    Route::resource('production-logs', ProductionLogController::class);
    Route::patch('production-logs/{productionLog}/validate', [ProductionLogController::class, 'validateAction'])
        ->name('production-logs.validate');

    Route::post('production-logs/{productionLog}/downtime-events', [DowntimeController::class, 'store'])
        ->name('production-logs.downtime-events.store');
    Route::patch('production-logs/{productionLog}/downtime-events/{downtimeEvent}', [DowntimeController::class, 'update'])
        ->name('production-logs.downtime-events.update');
    Route::delete('production-logs/{productionLog}/downtime-events/{downtimeEvent}', [DowntimeController::class, 'destroy'])
        ->name('production-logs.downtime-events.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/oee/dashboard', [OeeController::class, 'dashboard'])->name('oee.dashboard');
});

Route::middleware('auth')->group(function () {
    // GET /mrp — halaman dashboard MRP (AlertBanner + RopGauge + MrpGrid).
    // Path statis, didaftarkan di posisi teratas grup ini untuk konsistensi
    // dengan pola ordering statis-sebelum-wildcard yang sudah diterapkan
    // di seluruh project (lihat catatan di /schedules/{schedule}).
    Route::get('/mrp', [MrpController::class, 'dashboard'])
        ->name('mrp.dashboard');

    Route::post('/mrp/run', [MrpController::class, 'run'])
        ->name('mrp.run');
    Route::get('/mrp/alerts', [MrpController::class, 'alerts'])
        ->name('mrp.alerts');
    // Wildcard {mrpRun} didaftarkan SETELAH path statis /mrp/alerts dan
    // /mrp, konsisten dengan aturan ordering statis-sebelum-wildcard yang
    // sudah diterapkan di grup /schedules.
    Route::get('/mrp/runs/{mrpRun}', [MrpController::class, 'show'])
        ->name('mrp.runs.show');

    Route::get('/inventory/status', [InventoryController::class, 'status'])
        ->name('inventory.status');
});

Route::middleware('auth')->group(function () {
    Route::post('/schedules/run', [ScheduleController::class, 'run'])
        ->name('schedules.run');
    Route::post('/schedules/compare-all', [ScheduleController::class, 'compareAll'])
        ->name('schedules.compare-all');
    Route::post('/schedules/apply', [ScheduleController::class, 'apply'])
        ->name('schedules.apply');

    // GET /schedules/compare — merender halaman Schedules/Compare.vue.
    // Sebelumnya TIDAK ADA route apapun untuk ini (compareAll() controller
    // hanya return JSON untuk fetch API), sehingga tombol "↺ Bandingkan
    // Ulang" di Schedules/Show.vue (compareUrl default '/schedules/compare')
    // selalu 500 karena jatuh ke wildcard {schedule} di bawah dan mencoba
    // resolve "compare" sebagai bigint id. Ditambahkan sesi ini (utang
    // teknis item 1, lihat claude.md § Utang Teknis).
    //
    // array_values() WAJIB di sini: JobShopSchedulerService::compareAll()
    // mengembalikan array asosiatif ['spt' => Schedule, 'edd' => Schedule,
    // 'cr' => Schedule, 'fifo' => Schedule']. Tanpa array_values(), Inertia
    // akan serialize ini sebagai JSON object, bukan array — merusak
    // v-for/.map()/.find()/.sort() di Compare.vue yang mengasumsikan
    // props.results adalah array.
    Route::get('/schedules/compare', function (\Illuminate\Http\Request $request, \App\Services\Scheduling\JobShopSchedulerService $scheduler) {
        $startFrom = $request->query('start_from') ?? now();

        $results = $scheduler->compareAll(\Illuminate\Support\Carbon::parse($startFrom));

        return \Inertia\Inertia::render('Schedules/Compare', [
            'results'  => array_values($results),
            'indexUrl' => '/work-orders',
        ]);
    })->name('schedules.compare');

    // Route statis (compare-all, apply, run, compare) WAJIB didaftarkan
    // sebelum wildcard {schedule} di bawah ini -- kalau tidak, Laravel akan
    // menangkap path seperti "/schedules/compare" sebagai {schedule}="compare"
    // dan meledak saat dipaksa jadi bigint di query SQL.
    Route::get('/schedules/{schedule}', function (\App\Models\Schedule $schedule, \App\Services\Scheduling\GanttBuilderService $gantt) {
        $siblingIds = \App\Models\Schedule::query()
            ->where('scheduled_from', $schedule->scheduled_from)
            ->pluck('id', 'algorithm');

        return \Inertia\Inertia::render('Schedules/Show', [
            'initialData' => $gantt->build($schedule),
            'scheduleIds' => $siblingIds,
        ]);
    })->name('schedules.show');
});

Route::middleware('auth')->group(function () {
    Route::resource('work-centers', WorkCenterController::class)->except(['show']);
    Route::patch('work-centers/{workCenter}/toggle-active', [WorkCenterController::class, 'toggleActive'])
        ->name('work-centers.toggle-active');
});

Route::middleware('auth')->group(function () {
    Route::resource('materials', MaterialController::class)->except(['show']);
});

Route::middleware('auth')->group(function () {
    Route::resource('products', ProductController::class)->except(['show']);

    Route::post('products/{product}/bom', [ProductController::class, 'storeBom'])
        ->name('products.bom.store');
    Route::put('products/{product}/bom/{bom}', [ProductController::class, 'updateBom'])
        ->name('products.bom.update');
    Route::delete('products/{product}/bom/{bom}', [ProductController::class, 'destroyBom'])
        ->name('products.bom.destroy');

    Route::post('products/{product}/routings', [ProductController::class, 'storeRouting'])
        ->name('products.routings.store');
    Route::put('products/{product}/routings/{routing}', [ProductController::class, 'updateRouting'])
        ->name('products.routings.update');
    Route::delete('products/{product}/routings/{routing}', [ProductController::class, 'destroyRouting'])
        ->name('products.routings.destroy');
});

Route::middleware('auth')->group(function () {
    Route::post('/exports/schedule/{schedule}/pdf', [ExportController::class, 'schedulePdf'])
        ->name('exports.schedule.pdf');
    Route::get('/exports/download', [ExportController::class, 'download'])
        ->name('exports.download');
});

Route::middleware('auth')->group(function () {
    Route::post('/exports/schedule/{schedule}/pdf', [ExportController::class, 'schedulePdf'])
        ->name('exports.schedule.pdf');
    Route::get('/exports/schedule/{schedule}/pdf/status', [ExportController::class, 'schedulePdfStatus'])
        ->name('exports.schedule.pdf.status');
    Route::get('/exports/download', [ExportController::class, 'download'])
        ->name('exports.download');
});

Route::middleware('auth')->group(function () {
    Route::post('/exports/schedule/{schedule}/pdf', [ExportController::class, 'schedulePdf'])
        ->name('exports.schedule.pdf');
    Route::get('/exports/schedule/{schedule}/pdf/status', [ExportController::class, 'schedulePdfStatus'])
        ->name('exports.schedule.pdf.status');

    Route::post('/exports/oee/pdf', [ExportController::class, 'oeePdf'])
        ->name('exports.oee.pdf');
    Route::get('/exports/oee/pdf/status', [ExportController::class, 'oeePdfStatus'])
        ->name('exports.oee.pdf.status');

    Route::get('/exports/download', [ExportController::class, 'download'])
        ->name('exports.download');
});


Route::get('/oee/work-centers/{workCenter}/latest-snapshot', [OeeController::class, 'latestSnapshotWithBenchmark'])
    ->name('oee.latest-snapshot');

require __DIR__.'/auth.php';