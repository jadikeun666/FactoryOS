# FactoryOS — Production Intelligence Platform

Platform manufaktur berbasis web untuk pabrik menengah (50–500 karyawan).
Menggantikan workflow Excel + WhatsApp dengan tiga engine algoritma industrial:

**Engine 1 → Job Shop Scheduler**
**Engine 2 → OEE & Downtime Analytics**
**Engine 3 → Inventory Optimizer (MRP-lite)**

Target user: Production Manager, PPIC, Operator Lantai Produksi.

---

## Stack

| Layer        | Technology                          |
| ------------ | ----------------------------------- |
| Backend      | Laravel 11 (PHP 8.3+)              |
| Database     | PostgreSQL 16                       |
| Frontend     | Inertia.js + Vue 3 + Vite           |
| UI Library   | Tailwind CSS v3                     |
| Charts       | Chart.js (vue-chartjs) + D3.js      |
| Gantt        | Custom SVG via D3.js                |
| PDF Export   | barryvdh/laravel-dompdf             |
| Excel Export | Maatwebsite Laravel Excel           |
| Auth         | Laravel Breeze (Blade stack)        |
| Real-time    | Laravel Echo + Soketi (self-hosted) |
| Queue        | Laravel Queue (database driver)     |
| Precision    | PHP bcmath (semua kalkulasi kritis) |

> Auth: Breeze Blade stack — login/register adalah Blade biasa, semua halaman
> lain menggunakan Inertia + Vue 3. Ini intentional, jangan diubah.
>
> Tidak ada paid AI API. Semua intelligence adalah algoritma deterministik murni.

---

## Production Environment

| Item             | Value                                            |
| ---------------- | ------------------------------------------------ |
| OS               | Ubuntu 24.04 LTS via WSL2                        |
| URL              | http://factoryos.local                           |
| Project path     | `/home/user/workspace/factoryos/backend/laravel` |
| Queue workers    | 2 processes via Supervisor                       |
| WebSocket server | Soketi (self-hosted, gratis, Pusher-compatible)  |

### Commands

```bash
sudo supervisorctl status
sudo supervisorctl restart factoryos-worker:*
npm run build
php artisan test
php artisan schedule:run
npx soketi start
```

---

## Current Build Status

> **Update bagian ini setiap sesi sebelum mulai kerja.**

### ✅ Done
- Laravel 11 + Breeze (Blade stack) + PostgreSQL 16 terkonfigurasi
- Migration lengkap 17 tabel custom (5 master data + 4 Engine 1 + 4 Engine 2 + 6 Engine 3,
  sesuai docs/database.md)
- 19 Eloquent Models dengan relationship lengkap (belongsTo/hasMany antar semua entity)
- Database seeder jalan: 2 shift, 5 work center, 10 material, 3 product
  (dengan BOM & routing acak), 15 work order
- Kolom `users.role` ditambahkan (string biasa: admin, production_manager,
  ppic, operator — bukan Enum) + method isAdmin()/isProductionManager()/
  isPpic()/isOperator() di User model, masing-masing `$this->role === '...'`

- **Engine 1 — Job Shop Scheduler (SELESAI & teruji, backend sampai UI)**:
  - `SchedulingAlgorithmInterface` + 4 algoritma (SptAlgorithm, EddAlgorithm,
    CrAlgorithm, FifoAlgorithm) — semua pakai bcmath, score dalam string
  - `JobShopSchedulerService` (run, compareAll, computeMetrics) — precedence
    antar-operasi, kontensi mesin, dan metrik (makespan, tardiness, flow time)
    tervalidasi manual terhadap walkthrough di docs/scheduling.md
  - `SchedulingException` — guard circular dependency / data tidak konsisten
  - `WoOperationGeneratorService` — generate wo_operations dari routing produk,
    dengan idempotent-guard dan opsi force-regenerate
  - `WorkOrderOperationGenerationException`, `WorkOrderStatusException`
  - `WorkOrderStatusService` (`app/Services/WorkOrder/`) — validasi transisi
    status WO (draft→scheduled→in_progress→done/late, matrix
    ALLOWED_TRANSITIONS) dan guard penghapusan (FR-02)
  - `WorkOrderController` (thin controller) — CRUD, generate operations saat
    store, transisi status, regenerate operations manual
  - `StoreWorkOrderRequest`, `UpdateWorkOrderRequest`, `UpdateWorkOrderStatusRequest`
  - `WorkOrderPolicy` — update/delete hanya creator atau admin
  - Route `work-orders.*` (resource + update-status + regenerate-operations)
    ter-register di routes/web.php
  - `GanttBuilderService` (Schedule → JSON D3.js sesuai docs/gantt.md,
    termasuk `is_late` per WO & per assignment) — SELESAI & teruji
  - `ScheduleController` — `run`, `compareAll`, `ganttData`, `apply`
  - Endpoint `GET /api/schedules/{schedule}/gantt-data` (routes/api.php) —
    SELESAI
  - `ScheduleApplierService` — terapkan Schedule terpilih ke `wo_operations`
    (hanya operasi berstatus `pending`, operasi yang sudah running/done tidak
    ditimpa) + transisi status WO draft→scheduled via `WorkOrderStatusService`
    — SELESAI & teruji
  - `ScheduleApplyException`, `ApplyScheduleRequest`
  - Endpoint `POST /schedules/apply` (routes/web.php, middleware `auth`) —
    SELESAI
  - `GanttChart.vue` — Gantt interaktif berbasis D3 (toggle algoritma,
    tooltip, due-date line, zoom, klik-highlight WO)
  - `KpiCard.vue` — kartu metrik reusable dengan animasi count-up
  - `Compare.vue` — halaman perbandingan 4 algoritma + pemilihan + tombol
    "Terapkan Jadwal"
  - `Show.vue` — halaman detail satu schedule, membungkus `GanttChart.vue`
  - **23 test PASS**: 4 test class unit algoritma, 1 feature test
    `JobShopSchedulerService` (walkthrough 2 mesin/3 WO, compareAll, circular
    dependency guard, computeMetrics — 56 assertions), 1 unit test
    `GanttBuilderService` (2 test, 35 assertions, format docs/gantt.md +
    kasus is_late true/false), 1 feature test `ScheduleApplierService`
    (4 test: apply sukses & transisi WO, skip operasi non-pending, guard
    schedule tanpa assignments, guard schedule tidak ditemukan)
  - Tervalidasi manual di tinker: multi-WO dengan kontensi mesin nyata
    (3 WO rebutan 1 work center) — SPT/FIFO vs EDD/CR menghasilkan urutan
    berbeda sesuai kriteria masing-masing algoritma

- **Engine 2 — OEE & Downtime Analytics (fondasi backend SELESAI & teruji;
  dashboard/Pareto/trend/controller BELUM)**:
  - `OeeCalculatorService` (`app/Services/OEE/`) — hitung Availability,
    Performance (cap 1.0), Quality, OEE sesuai ISO 22400, bcmath scale 6.
    **PENTING**: bcmath native (`bcdiv`/`bcmul`) selalu truncate, bukan
    round — ditambahkan helper `round()` manual (round-half-up) di service
    ini supaya hasil match kalkulasi manual matematis biasa
  - `InvalidProductionLogException` — guard planned_minutes=0,
    actual_output=0, operating_time=0 (downtime = planned minutes)
  - `ProductionLogObserver` (`app/Observers/`) — dispatch `ProductionLogSaved`
    saat log dibuat, atau diupdate selama belum `is_validated`
  - `ProductionLogSaved` (event), `RecalculateOeeListener`,
    `RecalculateOeeJob` (queued) — alur lengkap sesuai docs/architecture.md
    § WebSocket Flow, registrasi manual di `AppServiceProvider::boot()`
    (Laravel 11 tidak pakai `EventServiceProvider` bawaan)
  - `OeeUpdated` event (`ShouldBroadcast`) — broadcast ke channel privat
    `work-center.{id}`, event name `oee.updated`, payload berisi snapshot
    lengkap (availability/performance/quality/oee sebagai string + computed_at)
  - `WorkCenterPolicy` (`app/Policies/`) — viewAny/view: semua user login;
    create/update/delete: admin only. Dipakai di `routes/channels.php`
    untuk otorisasi channel broadcast (auto-discovered oleh Laravel,
    tidak perlu register manual)
  - `config/broadcasting.php` dibuat dari nol (belum ada di scaffold
    Laravel 11 default) — driver `log` aktif (`BROADCAST_CONNECTION=log`),
    driver `pusher`/Soketi sudah disiapkan tapi belum diaktifkan
    (`composer require pusher/pusher-php-server` belum dijalankan)
  - `routes/channels.php` dibuat, di-register via `withRouting(channels: ...)`
    di `bootstrap/app.php` (JANGAN tambahkan `withBroadcasting()` juga —
    itu duplikat dan pernah menyebabkan channel routes ke-load dua kali)
  - `ProductionLogFactory` dibuat — default value PERSIS sama dengan
    contoh manual di docs/oee-formulas.md (planned=480, downtime=60,
    actual_output=380, good_output=370, ict=1.0) supaya reusable di test
    tanpa override
  - **16 test PASS**: 5 unit `OeeCalculatorServiceTest` (termasuk cap
    performance, 3 guard exception), 4 feature `ProductionLogObserverTest`
    (dispatch saat create/update, skip saat validated, job ter-queue),
    1 feature `RecalculateOeeJobTest` (job compute + broadcast),
    3 unit `WorkCenterPolicyTest`, 3 unit `OeeUpdatedTest` (channel,
    event name, payload shape)
  - **Catatan testing**: `QUEUE_CONNECTION=sync` di test env berarti
    Observer memicu job secara synchronous saat `ProductionLog::factory()->create()`.
    Test yang murni ingin menguji `OeeCalculatorService`/perilaku lain
    secara terisolasi WAJIB pakai `Event::fake([ProductionLogSaved::class])`
    di `setUp()`, kalau tidak exception validasi log bisa "bocor" duluan
    sebelum baris assert dijalankan.

### 🔄 In Progress
- (belum ada — siap mulai task baru)

### ⏳ Not Started
- Halaman Vue/Inertia `WorkOrders/{Index,Create,Show,Edit}.vue` — controller
  sudah render Inertia::render(...) tapi file .vue belum ada, jadi belum bisa
  diakses lewat browser
- Phase 3 — Engine 2 (OEE & Downtime): backend fondasi SUDAH SELESAI
  (lihat ✅ Done). Yang BELUM:
  - `OeeGauge.vue`, `ParetoChart.vue`, `OEE/Dashboard.vue` (live update via Echo)
  - Pareto Analysis algorithm (docs/oee-formulas.md § Pareto Analysis
    Downtime) — belum diimplementasi sama sekali
  - `trendData()` method (docs/oee-formulas.md § OEE Trend & Benchmark)
  - `benchmarkVsWorldClass()` method
  - `ProductionLogController` + `DowntimeController` (CRUD belum ada)
  - `StoreProductionLogRequest`, `UpdateProductionLogRequest`,
    `StoreDowntimeEventRequest`
  - `ProductionLogPolicy` (update hanya jika belum validated + creator)
  - Soketi belum benar-benar dijalankan/dites end-to-end (baru
    `BROADCAST_CONNECTION=log`, belum `pusher` + `npx soketi start`)
- Phase 4 — Engine 3 (Inventory/MRP): EoqCalculatorService, MrpService,
  CheckReorderAlertsJob
- Phase 5 — Integration & Export: Dashboard KPI lintas 3 engine,
  ExportService (PDF/Excel)

---

## Koreksi Dokumen

`docs/oee-formulas.md` dan `docs/engineering-rules.md` sebelumnya menyatakan
hasil OEE contoh manual = 0.771099. **Ini salah hitung di dokumen aslinya.**
Hasil yang benar secara matematis: 0.875000 × 0.904762 × 0.973684 = **0.770833**.
Sudah dikoreksi di kedua file docs tersebut dan di semua test terkait
(`OeeCalculatorServiceTest`, `RecalculateOeeJobTest`).

---

## Arsitektur Tiga Engine
┌─────────────────────────────────────────────────────────┐
│                      FactoryOS                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   ENGINE 1   │  │   ENGINE 2   │  │   ENGINE 3   │  │
│  │  Job Shop    │  │    OEE &     │  │  Inventory   │  │
│  │  Scheduler   │  │  Downtime    │  │  Optimizer   │  │
│  │  JSSP algo   │  │  ISO 22400   │  │  EOQ/SS/ROP  │  │
│  │  Gantt SVG   │  │  Pareto      │  │  MRP-lite    │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  │
│         └─────────────────┴─────────────────┘           │
│                     PostgreSQL                          │
└─────────────────────────────────────────────────────────┘

Keterkaitan:
- Engine 1 → jadwal produksi → Engine 2 bandingkan dengan aktual
- Engine 1 → kebutuhan material → Engine 3 cek stok & ROP
- Engine 3 → safety stock info → Engine 1 tahu material tersedia

---

## Documentation

Baca dokumen yang relevan **sebelum** mengimplementasi fitur apapun:

| File                        | Baca ketika mengerjakan...                           |
| --------------------------- | ---------------------------------------------------- |
| `docs/scheduling.md`        | Engine 1: JSSP, dispatching rules, Gantt             |
| `docs/oee-formulas.md`      | Engine 2: OEE, Pareto, downtime                      |
| `docs/inventory.md`         | Engine 3: EOQ, Safety Stock, ROP, MRP                |
| `docs/database.md`          | Migrations, models, queries, schema, indexing        |
| `docs/architecture.md`      | Services, observers, jobs, events, queues, WebSocket |
| `docs/gantt.md`             | D3.js Gantt: data format, interaction, SVG layout    |
| `docs/exports.md`           | PDF, Excel generation per engine                     |
| `docs/engineering-rules.md` | Presisi, bcmath, business rules, testing policy      |

---

## Main Services

| Service                   | Tanggung Jawab                                     | Status       |
| ------------------------- | --------------------------------------------------- | ------------ |
| `JobShopSchedulerService` | Jalankan 4 dispatching rules, simpan schedule       | ✅ Selesai   |
| `GanttBuilderService`     | Transform assignments → D3.js-ready dataset         | ✅ Selesai   |
| `ScheduleApplierService`  | Terapkan schedule terpilih ke wo_operations         | ✅ Selesai   |
| `OeeCalculatorService`    | Hitung OEE (✅). Pareto, trend data, benchmark (⏳)  | 🔄 Sebagian  |
| `EoqCalculatorService`    | EOQ, Safety Stock, ROP, Total Annual Cost (bcmath)  | ⏳ Belum     |
| `MrpService`              | MRP explosion: schedule → material requirements     | ⏳ Belum     |
| `ExportService`           | Orkestrasi PDF & Excel export per engine            | ⏳ Belum     |

---

## Formulas Quick Reference

ENGINE 1 — JOB SHOP SCHEDULING
SPT score   = processing_time (ascending)
EDD score   = due_date (ascending)
CR score    = (due_date - now).minutes / remaining_processing_time (ascending)
FIFO score  = work_order.created_at (ascending)
Makespan    = max(completion_time) semua operations
Tardiness_i = max(0, last_op_end_i - due_date_i)
Total Tard. = Σ Tardiness_i
Mean Flow   = Σ(last_op_end_i - release_date_i) / n
ENGINE 2 — OEE (ISO 22400)
Availability = (Planned - Downtime) / Planned
Performance  = (Output × IdealCycleTime) / OperatingTime  [cap 1.0]
Quality      = GoodOutput / TotalOutput
OEE          = Availability × Performance × Quality
Contoh manual tervalidasi: Availability=0.875000, Performance=0.904762,
Quality=0.973684, OEE=0.770833 (bukan 0.771099 — lihat § Koreksi Dokumen)
ENGINE 3 — INVENTORY
EOQ          = √(2 × D × S / H)
Safety Stock = Z × σ_d × √(LT)
ROP          = (avg_daily_demand × LT) + Safety Stock
Net Req(t)   = max(0, GrossReq(t) - ProjOnHand(t-1) - ScheduledReceipts(t))

---

## Catatan Teknis Penting (pelajaran dari sesi-sesi sebelumnya)

- **bcmath tidak pernah membulatkan**, selalu truncate. Untuk hasil yang
  perlu match kalkulasi manual matematis biasa (round half up), pakai
  helper `round()` manual seperti di `OeeCalculatorService` — jangan
  asumsikan `bcdiv`/`bcmul` otomatis akurat ke digit terakhir.
- **Laravel 11 tidak pakai `EventServiceProvider` bawaan** — event/listener
  diregister manual di `AppServiceProvider::boot()` via `Event::listen(...)`.
- **`bootstrap/app.php`**: `withRouting(channels: ...)` SUDAH cukup untuk
  meregister `routes/channels.php`. Jangan tambahkan `withBroadcasting()`
  juga — itu duplikat.
- **Policy di Laravel 11** auto-discovered selama nama file mengikuti
  konvensi `{Model}Policy` di `app/Policies/` — tidak perlu register manual
  di provider kecuali auto-discovery gagal.
- **Test yang meng-create model dengan Observer aktif** (`ProductionLog`,
  dll.) di environment dengan `QUEUE_CONNECTION=sync`: efek samping
  Observer/Event/Listener/Job akan langsung jalan synchronous saat
  `factory()->create()`. Kalau test tidak sedang menguji alur itu, isolasi
  dengan `Event::fake([...])` di `setUp()`.

---

## Roadmap per Phase

### Phase 1 — Foundation (Week 1–2) ✅ SELESAI
- [x] Laravel scaffolding + Breeze + Inertia + Vue 3
- [x] Semua migrations sekaligus
- [x] Models + relationships + factories
- [ ] Master data CRUD: WorkCenter, Product, Material (belum ada UI/Controller)
- [ ] BOM editor + Routing sequence editor
- [x] WorkOrder CRUD + generate wo_operations dari routing

### Phase 2 — Engine 1: Scheduler (Week 3–4) ✅ SELESAI
- [x] SchedulingAlgorithmInterface + 4 implementasi (SPT, EDD, CR, FIFO)
- [x] JobShopSchedulerService::run() dan compareAll()
- [x] GanttBuilderService → D3-ready JSON
- [x] GanttChart.vue interaktif + Compare.vue
- [x] ScheduleApplierService

### Phase 3 — Engine 2: OEE (Week 5–6) 🔄 SEBAGIAN
- [x] OeeCalculatorService (bcmath)
- [x] ProductionLogObserver + RecalculateOeeJob + broadcast infrastructure
- [x] WorkCenterPolicy + routes/channels.php
- [ ] Soketi benar-benar dijalankan & dites end-to-end (masih driver `log`)
- [ ] Pareto Analysis algorithm
- [ ] trendData() + benchmarkVsWorldClass()
- [ ] ProductionLogController + DowntimeController + Form Requests
- [ ] OeeGauge.vue, ParetoChart.vue, OEE/Dashboard.vue live update

### Phase 4 — Engine 3: Inventory (Week 7–8)
- [ ] EoqCalculatorService + MrpService (bcmath)
- [ ] RunMrpJob + CheckReorderAlertsJob (daily scheduler)
- [ ] RopGauge.vue, MrpGrid.vue, AlertBanner.vue

### Phase 5 — Integration & Polish (Week 9–10)
- [ ] Dashboard KPI lintas 3 engine
- [ ] Export PDF & Excel per engine
- [ ] Full test suite + canonical seeder (5 mesin, 3 produk, 10 material, 15 WO)

---

## Urutan Kerja Per Sesi

1. Update `Current Build Status`
2. Baca docs yang relevan
3. migration → model → factory → service → controller → Vue page
4. Unit test setiap Service sebelum lanjut
5. `php artisan test` sebelum selesai sesi