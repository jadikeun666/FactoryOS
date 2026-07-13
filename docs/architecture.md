# docs/architecture.md — Service Layer, Events, Jobs, WebSocket

## Prinsip Arsitektur

1. **Thin Controllers** — controller hanya: terima request, validasi via Form Request, delegasi ke Service, kembalikan response/redirect. Tidak ada business logic di controller.
2. **Service Layer** — semua business logic, algoritma, dan kalkulasi engineering ada di Services.
3. **Event-Driven** — side effects (recalculate, notify, log) dilakukan via Event → Listener, bukan direct call di controller.
4. **Observer Pattern** — trigger otomatis dari model lifecycle (saved, deleted) menggunakan Laravel Observers.
5. **PostgreSQL sebagai single source of truth** — tidak ada cache state yang bisa diverge dari DB.

---

## Service Layer Map

```
app/Services/
├── Scheduling/
│   ├── Contracts/
│   │   └── SchedulingAlgorithmInterface.php
│   ├── Algorithms/
│   │   ├── SptAlgorithm.php
│   │   ├── EddAlgorithm.php
│   │   ├── CrAlgorithm.php
│   │   └── FifoAlgorithm.php
│   ├── JobShopSchedulerService.php
│   └── GanttBuilderService.php
├── OEE/
│   └── OeeCalculatorService.php
├── Inventory/
│   ├── EoqCalculatorService.php
│   └── MrpService.php
└── ExportService.php
```

### Dependency Injection

Semua service di-inject via constructor. JANGAN `new ServiceName()` di dalam method.

```php
// Benar
class ScheduleController extends Controller {
    public function __construct(
        private readonly JobShopSchedulerService $scheduler,
        private readonly GanttBuilderService $gantt,
    ) {}
}

// Salah
public function run() {
    $scheduler = new JobShopSchedulerService(); // ← DILARANG
}
```

Bind di `AppServiceProvider` jika ada dependency kompleks:
```php
$this->app->bind(SchedulingAlgorithmInterface::class, SptAlgorithm::class);
```

---

## Controllers

```
app/Http/Controllers/
├── WorkCenterController.php     CRUD + toggle active
├── ProductController.php        CRUD + nested BOM & routing editor
├── MaterialController.php       CRUD + inventory params (EOQ inputs)
├── WorkOrderController.php      CRUD + status transitions + generate wo_operations
├── ScheduleController.php       POST run, POST compare-all, GET gantt-data
├── ProductionLogController.php  CRUD + validate action
├── DowntimeController.php       CRUD downtime_events dalam satu production_log
├── OeeController.php            GET dashboard, pareto, trend, benchmark
├── InventoryController.php      CRUD + list transactions + POST adjust
├── MrpController.php            POST run, GET grid, GET alerts
├── EoqController.php            POST calculate, GET summary
├── ExportController.php         GET pdf/excel per engine
└── DashboardController.php      GET summary KPI lintas engine
```

---

## Form Requests

```
app/Http/Requests/
├── StoreWorkCenterRequest.php
├── UpdateWorkCenterRequest.php
├── StoreProductRequest.php
├── UpdateProductRequest.php
├── StoreMaterialRequest.php
├── UpdateMaterialRequest.php
├── StoreWorkOrderRequest.php
├── UpdateWorkOrderRequest.php
├── StoreProductionLogRequest.php
├── UpdateProductionLogRequest.php
├── StoreDowntimeEventRequest.php
├── StoreInventoryParamRequest.php
└── RunScheduleRequest.php          -- validasi: algorithm ENUM, startFrom date
```

---

## Observers

```php
// app/Observers/ProductionLogObserver.php
class ProductionLogObserver {
    public function created(ProductionLog $log): void {
        ProductionLogSaved::dispatch($log);
    }
    public function updated(ProductionLog $log): void {
        if ($log->is_validated) return; // validated log tidak bisa trigger recalc
        ProductionLogSaved::dispatch($log);
    }
}

// app/Observers/InventoryObserver.php
class InventoryObserver {
    public function updated(Inventory $inventory): void {
        InventoryTransacted::dispatch($inventory);
    }
}

// Daftarkan di AppServiceProvider atau EventServiceProvider:
ProductionLog::observe(ProductionLogObserver::class);
Inventory::observe(InventoryObserver::class);
```

---

## Events & Listeners

| Event | Dispatch oleh | Listener(s) |
|---|---|---|
| `ProductionLogSaved` | `ProductionLogObserver` | `RecalculateOeeListener` |
| `OeeUpdated` | `RecalculateOeeJob` | broadcast via Soketi |
| `ScheduleCreated` | `ScheduleController` | `TriggerMrpRunListener`, `LogScheduleActivity` |
| `MrpRunCompleted` | `RunMrpJob` | `CheckReorderAlertsListener`, `LogMrpActivity` |
| `InventoryTransacted` | `InventoryObserver` | `UpdateReorderAlertsListener` |
| `ReorderAlertRaised` | `CheckReorderAlertsJob` | `NotifyPpicListener` |

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    ProductionLogSaved::class   => [RecalculateOeeListener::class],
    ScheduleCreated::class      => [TriggerMrpRunListener::class, LogScheduleActivity::class],
    MrpRunCompleted::class      => [CheckReorderAlertsListener::class, LogMrpActivity::class],
    InventoryTransacted::class  => [UpdateReorderAlertsListener::class],
    ReorderAlertRaised::class   => [NotifyPpicListener::class],
];
```

---

## Jobs (Queued)

| Job | Dipicu oleh | Queue | Aksi |
|---|---|---|---|
| `RunSchedulingJob` | `ScheduleController` | default | Jalankan 1 algoritma, simpan schedule |
| `RecalculateOeeJob` | `RecalculateOeeListener` | default | Hitung OEE, broadcast |
| `RunMrpJob` | `TriggerMrpRunListener` | default | BOM explosion → requirements |
| `CheckReorderAlertsJob` | Laravel Scheduler (06:00) | default | Scan semua material vs ROP |
| `GeneratePdfReportJob` | `ExportController` | exports | Render DomPDF, simpan ke storage |
| `GenerateExcelReportJob` | `ExportController` | exports | Generate Excel, simpan ke storage |

```php
// app/Jobs/RecalculateOeeJob.php
class RecalculateOeeJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10; // detik

    public function __construct(private readonly ProductionLog $log) {}

    public function handle(OeeCalculatorService $calculator): void {
        $snapshot = $calculator->compute($this->log);
        broadcast(new OeeUpdated($snapshot))->toOthers();
    }

    public function failed(\Throwable $e): void {
        Log::error('RecalculateOeeJob failed', [
            'log_id' => $this->log->id,
            'error'  => $e->getMessage(),
        ]);
    }
}
```

---

## Laravel Scheduler

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void {
    // Cek reorder alerts setiap hari pukul 06:00
    $schedule->job(new CheckReorderAlertsJob())->dailyAt('06:00');

    // Opsional: auto-recalculate EOQ setiap minggu Senin
    $schedule->call(function () {
        Material::all()->each(fn($m) => EoqCalculatorService::computeAndSave($m));
    })->weekly()->mondays()->at('07:00');
}
```

---

## WebSocket Flow (Soketi)

```
Client (Vue)                   Laravel                    Soketi
    |                             |                          |
    |-- Echo.private(channel) --> | (subscribe)              |
    |                             |                          |
    | [operator submit log]       |                          |
    |-- POST /production-logs --> |                          |
    |                             |-- dispatch Job --------> |
    |                             |   RecalculateOeeJob      |
    |                             |     compute OEE          |
    |                             |     broadcast(OeeUpdated)|
    |                             |------------------------->|
    |<-- push event --------------|--------------------------|
    | 'oee.updated' payload       |                          |
    | { snapshot data }           |                          |
    |                             |                          |
    [OeeGauge.vue reactive update]
```

### Channel Authorization
```php
// routes/channels.php
Broadcast::channel('work-center.{workCenterId}', function ($user, $workCenterId) {
    return $user->can('view', WorkCenter::find($workCenterId));
});
```

### Vue Echo Listener
```js
// dalam OEE/Dashboard.vue setup()
onMounted(() => {
  Echo.private(`work-center.${props.workCenterId}`)
    .listen('OeeUpdated', (e) => {
      oeeData.value = e.snapshot
    })
})

onUnmounted(() => {
  Echo.leave(`work-center.${props.workCenterId}`)
})
```

---

## Policies

```
app/Policies/
├── WorkOrderPolicy.php         -- update/delete hanya creator atau admin
├── ProductionLogPolicy.php     -- update hanya jika belum validated + creator
├── SchedulePolicy.php          -- delete schedule hanya admin
└── ExportPolicy.php            -- export hanya jika ada schedule applied
```

---

## Inertia Shared Data

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array {
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user()?->only('id', 'name', 'email'),
        ],
        'flash' => [
            'success' => session('success'),
            'error'   => session('error'),
        ],
    ]);
}
```
