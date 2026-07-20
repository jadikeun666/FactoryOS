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
| Backend      | Laravel 12.63.0 (PHP 8.5)           |
| Database     | PostgreSQL 16                       |
| Frontend     | Inertia.js v3 + Vue 3 + Vite        |
| UI Library   | Tailwind CSS v3 (custom scoped CSS per komponen, bukan utility-first murni) |
| Charts       | D3.js (Gantt, Pareto, trend chart — semua custom SVG, bukan Chart.js) |
| Gantt        | Custom SVG via D3.js                |
| PDF Export   | barryvdh/laravel-dompdf (belum diinstall) |
| Excel Export | Maatwebsite Laravel Excel (belum diinstall) |
| Auth         | Laravel Breeze (Blade stack)        |
| Real-time    | Laravel Echo + Soketi (self-hosted, terpasang tapi belum aktif) |
| API Auth     | Laravel Sanctum v4.3.2 (stateful, session cookie — bukan token) |
| Queue        | Laravel Queue (perlu verifikasi driver non-test, lihat § Utang Teknis) |
| Precision    | PHP bcmath (semua kalkulasi kritis) |

> Auth: Breeze Blade stack — login/register adalah Blade biasa
> (`resources/js/app.js`, layout `layouts/app.blade.php`/`layouts/guest.blade.php`),
> sedangkan semua halaman lain menggunakan Inertia + Vue 3 (entry terpisah
> `resources/js/inertia-app.js`, root view `resources/views/app.blade.php`).
> Dua entry point ini SENGAJA terpisah — jangan digabung.
>
> Tidak ada paid AI API. Semua intelligence adalah algoritma deterministik murni.

---

## ⚠️ Koreksi Penting (2026-07-19)

Sesi-sesi sebelumnya menandai beberapa hal sebagai "SELESAI & teruji" yang
ternyata **tidak pernah benar-benar bisa dijalankan/diverifikasi di browser**.
Pelajaran untuk sesi berikutnya: **"kode sudah ditulis" ≠ "sudah bekerja"**
— selalu verifikasi end-to-end (build + buka browser + cek Network/Console),
jangan cuma percaya status di dokumen ini tanpa cek ulang kalau ada keraguan.

Yang ditemukan salah/hilang:
1. **Versi Laravel salah tercatat sebagai "11" di seluruh dokumen** —
   versi sebenarnya sudah **12.63.0** sejak awal project. Semua referensi
   "Laravel 11" di dokumen ini sudah dikoreksi jadi "Laravel 12".
2. **Inertia tidak pernah ter-bootstrap** — `inertiajs/inertia-laravel`
   (composer) dan `vue`/`@inertiajs/vue3` (npm) belum ter-install sama
   sekali, meski Engine 1 sudah menulis banyak file `.vue` dan menandainya
   "SELESAI & teruji". File-file itu tidak bisa render sampai sesi ini.
3. **Laravel Sanctum tidak ter-install**, meski endpoint
   `GET /api/schedules/{schedule}/gantt-data` sudah pakai middleware
   `auth:sanctum` dan ditandai "SELESAI".
4. **Beberapa route halaman tidak pernah didaftarkan** meski controller &
   Vue page-nya sudah lengkap: `schedules.run`, `schedules.compare-all`,
   `schedules.show` semuanya hilang dari `routes/web.php`.

Semua ini sudah diperbaiki di sesi 2026-07-19 (lihat detail di § Current
Build Status). **Rekomendasi untuk sesi berikutnya**: sebelum menandai
sesuatu "SELESAI", verifikasi dengan benar-benar membuka browser dan
mengecek Network/Console tab, bukan hanya membaca kode atau menjalankan
`php artisan test` (unit/feature test tidak menangkap masalah bootstrap
frontend seperti ini).

---

## Production Environment

| Item             | Value                                            |
| ---------------- | ------------------------------------------------ |
| OS               | Ubuntu 24.04 LTS via WSL2                        |
| URL (dev)        | http://127.0.0.1:8000 (via `php artisan serve`)  |
| Project path     | `~/workspace/factoryos/laravel`                  |
| Queue workers    | Belum diverifikasi di luar test env — lihat § Utang Teknis |
| WebSocket server | Soketi (terpasang di sisi client, belum dijalankan — `BROADCAST_CONNECTION=log`) |

### Commands

```bash
npm run build
npm run dev
php artisan serve
php artisan test
php artisan migrate
php artisan tinker
```

Perintah lama yang tercatat di versi dokumen sebelumnya
(`sudo supervisorctl`, `http://factoryos.local`, `npx soketi start`)
**belum terverifikasi ada/jalan di environment ini** — jangan asumsikan
tersedia tanpa cek dulu.

---

## Current Build Status

> **Update bagian ini setiap sesi sebelum mulai kerja.**

### ✅ Done

**Foundation**
- Laravel 12.63.0 + Breeze (Blade stack) + PostgreSQL 16 terkonfigurasi
- Migration lengkap 17 tabel custom (5 master data + 4 Engine 1 + 4 Engine 2 + 6 Engine 3, sesuai `docs/database.md`)
- 19 Eloquent Models dengan relationship lengkap (belongsTo/hasMany antar semua entity)
- Database seeder jalan: 2 shift, 5 work center, 10 material, 3 product (dengan BOM & routing acak), 15 work order
- Kolom `users.role` (string biasa: admin, production_manager, ppic, operator) + method `isAdmin()`/`isProductionManager()`/`isPpic()`/`isOperator()` di `User` model

**Fondasi Frontend Inertia + Sanctum (dibangun dari nol di sesi 2026-07-19 — lihat § Koreksi Penting)**
- `inertiajs/inertia-laravel:^2.0` (composer) → resolve ke **Inertia v3** (breaking change dari v2: format `@inertia`/`@inertiaHead` beda dari yang didokumentasikan sebelumnya)
- `@inertiajs/vue3 ^3.6.1`, `vue ^3.5.40`, `@vitejs/plugin-vue` (npm)
- `resources/views/app.blade.php` — root view Inertia (baru)
- `resources/js/inertia-app.js` — entry point Inertia terpisah dari `resources/js/app.js` lama (tetap dipakai Blade/Alpine untuk halaman auth Breeze — **jangan digabung**)
- `app/Http/Middleware/HandleInertiaRequests.php` terdaftar di `bootstrap/app.php` via `$middleware->web(append: [...])`
- `vite.config.js` — tambah plugin `@vitejs/plugin-vue` + entry kedua `resources/js/inertia-app.js`
- `laravel/sanctum v4.3.2` terpasang, migrasi `personal_access_tokens` dijalankan
- `Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful` ditambahkan ke grup middleware `api` di `bootstrap/app.php` (`$middleware->api(prepend: [...])`) — ini yang membuat `fetch()` dari Vue (session cookie Breeze) dianggap terautentikasi oleh guard `sanctum`, tanpa token API asli
- `config/sanctum.php` default `stateful` domains sudah otomatis mencakup `127.0.0.1:8000` — tidak perlu isi `SANCTUM_STATEFUL_DOMAINS` manual untuk environment lokal
- Route yang sebelumnya hilang sudah ditambahkan ke `routes/web.php`: `schedules.run` (POST), `schedules.compare-all` (POST), `schedules.show` (GET, saat ini closure inline — **utang teknis**: pindahkan jadi method `ScheduleController::show()`)
- **Penting**: route statis (`/schedules/compare-all`, `/schedules/apply`, `/schedules/run`) WAJIB didaftarkan SEBELUM route wildcard `/schedules/{schedule}` — kalau tidak, Laravel menangkap `/schedules/compare` sebagai `{schedule}="compare"` dan meledak saat dipaksa jadi bigint di query SQL
- Terverifikasi nyata di browser: toggle SPT/EDD/CR/FIFO di halaman Schedule menghasilkan request `gantt-data` 200 di Network tab

**Engine 1 — Job Shop Scheduler (backend SELESAI & teruji; frontend Vue sudah ada file-nya, sekarang benar-benar bisa render setelah fondasi Inertia dibangun)**
- `SchedulingAlgorithmInterface` + 4 algoritma (SptAlgorithm, EddAlgorithm, CrAlgorithm, FifoAlgorithm) — semua pakai bcmath, score dalam string
- `JobShopSchedulerService` (run, compareAll, computeMetrics) — precedence antar-operasi, kontensi mesin, dan metrik (makespan, tardiness, flow time) tervalidasi manual terhadap walkthrough di `docs/scheduling.md`
- `SchedulingException` — guard circular dependency / data tidak konsisten
- `WoOperationGeneratorService` — generate `wo_operations` dari routing produk, dengan idempotent-guard dan opsi force-regenerate
- `WorkOrderOperationGenerationException`, `WorkOrderStatusException`
- `WorkOrderStatusService` — validasi transisi status WO (draft→scheduled→in_progress→done/late) dan guard penghapusan (FR-02)
- `WorkOrderController` (thin controller) — CRUD, generate operations saat store, transisi status, regenerate operations manual
- `StoreWorkOrderRequest`, `UpdateWorkOrderRequest`, `UpdateWorkOrderStatusRequest`
- `WorkOrderPolicy` — update/delete hanya creator atau admin
- Route `work-orders.*` ter-register di `routes/web.php`
- `GanttBuilderService` (Schedule → JSON D3.js sesuai `docs/gantt.md`, termasuk `is_late` per WO & per assignment)
- `ScheduleController` — `run`, `compareAll`, `ganttData`, `apply` (semua thin, delegasi penuh ke Service)
- Endpoint `GET /api/schedules/{schedule}/gantt-data` — **terverifikasi jalan nyata di browser** (lihat di atas)
- `ScheduleApplierService` — terapkan Schedule terpilih ke `wo_operations` (hanya operasi `pending`) + transisi status WO via `WorkOrderStatusService`
- `ScheduleApplyException`, `ApplyScheduleRequest`
- `GanttChart.vue` — Gantt interaktif berbasis D3 (toggle algoritma, tooltip, due-date line, zoom, klik-highlight WO) — **terverifikasi render di browser**
- `KpiCard.vue` — kartu metrik reusable dengan animasi count-up
- `Schedules/Compare.vue` — halaman perbandingan 4 algoritma + tombol "Terapkan Jadwal". **Bug ditemukan & diperbaiki**: memakai `target.schedule_id` padahal `Schedule` model attribute-nya `id` (bukan `schedule_id`) — tombol "Terapkan Jadwal" sebelumnya mengirim `schedule_id: undefined`. Sudah diperbaiki jadi `target.id`.
- `Schedules/Show.vue` — halaman detail satu schedule, membungkus `GanttChart.vue` — **terverifikasi render di browser**
- 23 test PASS (unit algoritma, feature `JobShopSchedulerService`, unit `GanttBuilderService`, feature `ScheduleApplierService`)

**Engine 2 — OEE & Downtime Analytics (backend DAN frontend SELESAI & teruji end-to-end)**

*Backend:*
- `OeeCalculatorService` — Availability, Performance (cap 1.0), Quality, OEE sesuai ISO 22400, bcmath scale 6. bcmath native selalu truncate — helper `round()` manual (round-half-up) memastikan hasil match kalkulasi manual matematis
- `OeeCalculatorService::trendData()` — rata-rata OEE harian per mesin (multi-shift per tanggal), `INTERNAL_SCALE=12` sebelum round ke `SCALE=6`, `whereDate()` untuk filter rentang tanggal
- `OeeCalculatorService::benchmarkVsWorldClass()` — gap actual vs target world class (OEE 85%, Availability 90%, Performance 95%, Quality 99.99%), helper `roundSigned()` (round half-up aware nilai negatif)
- `InvalidProductionLogException` — guard `planned_minutes=0`, `actual_output=0`, `operating_time=0`
- `DowntimeAnalysisService::paretoDowntime()` sesuai `docs/oee-formulas.md § Pareto Analysis Downtime`. Dipisah dari `OeeCalculatorService` (domain berbeda: agregat cross-log vs per-record). `INTERNAL_SCALE = SCALE + 4`, `cumulative` diakumulasi dari nilai raw presisi tinggi untuk mencegah compounding rounding error
- `ProductionLogController` (thin, Inertia) — CRUD + `validateAction()`
- `DowntimeController` (thin) — CRUD `downtime_events`, otorisasi didelegasikan ke `ProductionLogPolicy::update()` milik parent log
- `ProductionLogPolicy` — update/delete ditolak jika `is_validated=true`; `validateLog()` boleh creator/admin/production_manager
- `StoreProductionLogRequest` (validasi nested `downtime_events.*`), `UpdateProductionLogRequest`, `StoreDowntimeEventRequest`
- Route `production-logs.*` ter-register di `routes/web.php`
- `ProductionLogObserver` — dispatch `ProductionLogSaved` saat log dibuat/diupdate (selama belum `is_validated`)
- `ProductionLogSaved` (event), `RecalculateOeeListener`, `RecalculateOeeJob` (queued) — registrasi manual di `AppServiceProvider::boot()` (Laravel 12 tidak pakai `EventServiceProvider` bawaan)
- `OeeUpdated` event (`ShouldBroadcast`) — broadcast ke channel privat `work-center.{id}`, event name `oee.updated` (via `broadcastAs()` custom), payload `{ snapshot: { work_center_id, log_date, shift_id, availability, performance, quality, oee, computed_at } }`
- `WorkCenterPolicy` — viewAny/view: semua user login; create/update/delete: admin only. Dipakai di `routes/channels.php`
- `config/broadcasting.php` — driver `log` aktif (`BROADCAST_CONNECTION=log`), driver `pusher`/Soketi sudah disiapkan di client tapi belum diaktifkan di server
- `routes/channels.php` di-register via `withRouting(channels: ...)` di `bootstrap/app.php` — **jangan tambahkan `withBroadcasting()` juga**, itu duplikat
- `ProductionLogFactory` — default value sama dengan contoh manual `docs/oee-formulas.md`
- **Fix**: base `Controller` sekarang `use AuthorizesRequests;` (sebelumnya hilang, bikin `$this->authorize()` gagal di semua controller)
- **Fix**: query rentang tanggal WAJIB pakai `whereDate()`, bukan `whereBetween()` string polos (lihat detail teknis di § Catatan Teknis Penting)
- `OeeController` (baru, sesi 2026-07-19) — `dashboard()`, `pareto()`, `trend()`, `benchmark()`, `latestSnapshotWithBenchmark()`. Pola validasi inline `$request->validate()` (bukan Form Request terpisah), mengikuti pola nyata `ScheduleController`
  - `latestSnapshotWithBenchmark()` ditambahkan supaya benchmark card di `OEE/Dashboard.vue` akurat saat ganti mesin (awalnya di-derive dari titik trend terakhir yang cuma rata-rata harian, tidak presisi untuk benchmark per-shift)
- Routes: `oee.dashboard` (web.php); `oee.pareto`, `oee.trend`, `oee.benchmark`, `oee.latest-snapshot` (api.php, grup `auth:sanctum`)
- 56 test PASS (Engine 2 backend): unit `OeeCalculatorServiceTest` (5), unit `OeeCalculatorServiceTrendAndBenchmarkTest` (6), unit `DowntimeAnalysisServiceTest` (4), feature `ProductionLogObserverTest` (4), feature `RecalculateOeeJobTest` (1), unit `WorkCenterPolicyTest` (3), unit `OeeUpdatedTest` (3), feature `ProductionLogControllerTest` (8), feature `DowntimeControllerTest` (5)

*Frontend (baru, sesi 2026-07-19 — SELESAI & teruji end-to-end di browser):*
- `ProductionLogs/{Index,Create,Show,Edit}.vue` — CRUD lengkap + downtime events. Terverifikasi via skrip E2E Playwright ad-hoc (`e2e-production-logs.mjs` di root project, tidak permanen, boleh dihapus kapan saja): Index/Create/Show OK, Edit menghasilkan 403 yang **benar sesuai desain** immutability (log `is_validated=true` atau bukan creator/admin) — bukan bug
- `OeeGauge.vue` — gauge arc SVG + sub-metrics bar (Availability/Performance/Quality), live update via Laravel Echo (channel `work-center.{id}`, event `.oee.updated` — **titik di depan wajib** karena `broadcastAs()` custom name, bukan default namespaced Laravel)
- `resources/js/echo.js` (baru) — konfigurasi Echo untuk Soketi. **Bug ditemukan & diperbaiki**: fallback env var awalnya pakai `??` (nullish coalescing) yang TIDAK menangkap string kosong `""` (hanya `null`/`undefined`) — kalau `.env` pakai sintaks interpolasi `${VAR}` yang gagal resolve jadi string kosong, Echo diam-diam mencoba konek ke domain `pusher.com` asli. Diperbaiki dengan helper `envOrDefault()` + guard: kalau `VITE_PUSHER_APP_KEY` kosong, Echo **tidak diinisialisasi sama sekali** (window.Echo tetap undefined, komponen tampil "Offline" dengan sengaja)
- `ParetoChart.vue` — bar chart + garis kumulatif + garis threshold 80%, fetch via `/api/oee/pareto`, filter tanggal reaktif
- `OEE/Dashboard.vue` — gabungan `OeeGauge` + benchmark card + trend chart (4 garis: OEE/Availability/Performance/Quality, dengan circle marker per titik supaya data 1 titik tetap terlihat — d3 `<path>` butuh minimal 2 titik untuk tergambar) + `ParetoChart`. Ganti dropdown mesin memicu fetch ulang trend + snapshot + benchmark
- Soketi masih **belum dijalankan nyata** (`BROADCAST_CONNECTION=log`) — semua komponen Echo sudah siap pakai, tinggal aktivasi di sesi lain tanpa perlu ubah kode Vue

**Engine 3 — Inventory Optimizer (baru dimulai, sesi 2026-07-19)**
- `EoqCalculatorService` (`app/Services/Inventory/`) — `computeEoq()`, `computeSafetyStock()`, `computeRop()`, `computeTotalAnnualCost()`, `computeAndSave()`. bcmath scale 6, `INTERNAL_SCALE = SCALE + 4`, `bcSqrt()` Newton-Raphson dengan guard `n=0` (hasil 0, bukan div-by-zero), `round()` half-up manual (pola identik `OeeCalculatorService`/`DowntimeAnalysisService`)
- 5 test PASS (`EoqCalculatorServiceTest`): EOQ (268.328157 — beda dari contoh docs 268.3281 karena docs pakai truncate 4 desimal sedangkan service ini round 6 desimal, keduanya benar untuk metode masing-masing), EOQ guard `demand=0`, Safety Stock, ROP, Total Annual Cost (validasi properti EOQ: ordering cost = holding cost di titik optimal)
- Test pakai `new InventoryParam([...])` langsung (bukan factory/DB) karena method yang diuji murni baca attribute in-memory. `computeAndSave()` **belum ada test** (butuh `RefreshDatabase`, feature test territory)

**Total: 96 test PASS (265 assertions), full suite, tidak ada regresi ke Engine 1/2 dari perubahan sesi 2026-07-19**

### 🔄 In Progress
- (belum ada — siap mulai task baru)

### ⏳ Not Started
- Halaman Vue/Inertia `WorkOrders/{Index,Create,Show,Edit}.vue` — controller sudah render `Inertia::render(...)` tapi file `.vue` belum ada
- Master data CRUD: WorkCenter, Product, Material (belum ada UI/Controller)
- BOM editor + Routing sequence editor
- Soketi belum benar-benar dijalankan end-to-end (`BROADCAST_CONNECTION` masih `log`; isi `VITE_PUSHER_*` di `.env` + `npx soketi start` untuk aktivasi — `OeeGauge.vue` akan otomatis tersambung tanpa ubah kode)
- `ScheduleController::show()` masih closure inline di `routes/web.php` — sebaiknya dipindah jadi method controller yang sesungguhnya
- `MrpService` (BOM explosion, period-by-period netting sesuai `docs/inventory.md`)
- `RunMrpJob`, `CheckReorderAlertsJob` (daily scheduler)
- `RopGauge.vue`, `MrpGrid.vue`, `AlertBanner.vue`
- `ExportService` (PDF/Excel per engine) — `barryvdh/laravel-dompdf` dan `maatwebsite/excel` belum di-`composer require`
- Dashboard KPI lintas 3 engine
- Feature test untuk `OeeController` dan `EoqCalculatorService::computeAndSave()`

---

## ⚠️ Utang Teknis / Perlu Investigasi

1. **`oee_snapshots` kosong untuk hampir semua `ProductionLog` historis** —
   pipeline `ProductionLogObserver → RecalculateOeeJob → OeeCalculatorService::compute()`
   kemungkinan tidak berjalan otomatis untuk data yang sudah ada. Perlu cek
   `QUEUE_CONNECTION` di `.env` (bukan `.env.testing`) — kalau bukan `sync`
   dan tidak ada queue worker yang jalan, job akan menumpuk di tabel `jobs`
   tanpa pernah diproses. Verifikasi dengan:
   ```bash
   cat .env | grep QUEUE_CONNECTION
   php artisan queue:work --once   # kalau QUEUE_CONNECTION=database
   ```
2. **Tidak ada supervisor/process manager terverifikasi** untuk queue worker
   di environment ini — dokumentasi lama menyebut Supervisor tapi belum
   pernah dicek benar-benar terpasang.
3. `e2e-production-logs.mjs` di root project adalah skrip diagnostik ad-hoc
   (Playwright), bukan bagian dari test suite permanen — aman dihapus, atau
   bisa dipertahankan sebagai referensi pola E2E untuk halaman lain.

---

## Koreksi Dokumen (formula)

`docs/oee-formulas.md` dan `docs/engineering-rules.md` sebelumnya menyatakan
hasil OEE contoh manual = 0.771099. **Ini salah hitung di dokumen aslinya.**
Hasil yang benar secara matematis: 0.875000 × 0.904762 × 0.973684 = **0.770833**.
Sudah dikoreksi di kedua file docs tersebut dan di semua test terkait.

---

## Arsitektur Tiga Engine

```
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
```

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

**Catatan**: beberapa hal di `docs/architecture.md` (mis. nama Form Request
`RunScheduleRequest`) ternyata tidak sesuai implementasi nyata — controller
yang sudah ada pakai validasi inline `$request->validate()`, bukan Form
Request terpisah. Kalau ragu, cek kode controller yang sudah ada dulu
sebelum asumsi dari docs.

---

## Main Services

| Service                   | Tanggung Jawab                                     | Status       |
| ------------------------- | --------------------------------------------------- | ------------ |
| `JobShopSchedulerService` | Jalankan 4 dispatching rules, simpan schedule       | ✅ Selesai   |
| `GanttBuilderService`     | Transform assignments → D3.js-ready dataset         | ✅ Selesai   |
| `ScheduleApplierService`  | Terapkan schedule terpilih ke wo_operations         | ✅ Selesai   |
| `OeeCalculatorService`    | Hitung OEE, trend data, benchmark vs world class    | ✅ Selesai   |
| `DowntimeAnalysisService` | Pareto analysis downtime (agregat cross-log)        | ✅ Selesai   |
| `EoqCalculatorService`    | EOQ, Safety Stock, ROP, Total Annual Cost (bcmath)  | ✅ Selesai   |
| `MrpService`              | MRP explosion: schedule → material requirements     | ⏳ Belum     |
| `ExportService`           | Orkestrasi PDF & Excel export per engine            | ⏳ Belum     |

---

## Formulas Quick Reference

**ENGINE 1 — JOB SHOP SCHEDULING**
```
SPT score   = processing_time (ascending)
EDD score   = due_date (ascending)
CR score    = (due_date - now).minutes / remaining_processing_time (ascending)
FIFO score  = work_order.created_at (ascending)
Makespan    = max(completion_time) semua operations
Tardiness_i = max(0, last_op_end_i - due_date_i)
Total Tard. = Σ Tardiness_i
Mean Flow   = Σ(last_op_end_i - release_date_i) / n
```

**ENGINE 2 — OEE (ISO 22400)**
```
Availability = (Planned - Downtime) / Planned
Performance  = (Output × IdealCycleTime) / OperatingTime  [cap 1.0]
Quality      = GoodOutput / TotalOutput
OEE          = Availability × Performance × Quality
```
Contoh manual tervalidasi: Availability=0.875000, Performance=0.904762,
Quality=0.973684, OEE=0.770833 (bukan 0.771099 — lihat § Koreksi Dokumen).

**ENGINE 3 — INVENTORY**
```
EOQ          = √(2 × D × S / H)
Safety Stock = Z × σ_d × √(LT)
ROP          = (avg_daily_demand × LT) + Safety Stock
Net Req(t)   = max(0, GrossReq(t) - ProjOnHand(t-1) - ScheduledReceipts(t))
```
Contoh manual tervalidasi (`EoqCalculatorServiceTest`):
D=1200, S=150000, H=5000 → EOQ=268.328157;
Z=1.6450, σ_d=3, LT=7 → Safety Stock=13.056783, ROP=83.056783
(dengan avg_daily_demand=10, yaitu annual_demand=3650).

---

## Catatan Teknis Penting (pelajaran dari sesi-sesi sebelumnya)

- **bcmath tidak pernah membulatkan**, selalu truncate. Untuk hasil yang
  perlu match kalkulasi manual matematis biasa (round half up), pakai
  helper `round()` manual seperti di `OeeCalculatorService`/
  `DowntimeAnalysisService`/`EoqCalculatorService` — jangan asumsikan
  `bcdiv`/`bcmul` otomatis akurat ke digit terakhir.
- **Laravel 12 tidak pakai `EventServiceProvider` bawaan** — event/listener
  diregister manual di `AppServiceProvider::boot()` via `Event::listen(...)`.
- **`bootstrap/app.php`**: `withRouting(channels: ...)` sudah cukup untuk
  meregister `routes/channels.php`. Jangan tambahkan `withBroadcasting()`
  juga — itu duplikat.
- **`bootstrap/app.php` middleware**: `HandleInertiaRequests` didaftarkan
  via `$middleware->web(append: [...])`; `EnsureFrontendRequestsAreStateful`
  (Sanctum) via `$middleware->api(prepend: [...])`. Keduanya perlu ada
  untuk Inertia dan endpoint API stateful bekerja.
- **Route statis vs wildcard**: route path statis (`/schedules/compare-all`,
  dst) WAJIB didaftarkan SEBELUM route wildcard dengan pola sama
  (`/schedules/{schedule}`), kalau tidak Laravel salah menangkap string
  literal sebagai parameter dan meledak saat dipaksa jadi tipe kolom (mis.
  bigint) di query.
- **Policy di Laravel 12** auto-discovered selama nama file mengikuti
  konvensi `{Model}Policy` di `app/Policies/` — tidak perlu register manual
  di provider kecuali auto-discovery gagal.
- **Test yang meng-create model dengan Observer aktif** (`ProductionLog`,
  dll.) di environment dengan `QUEUE_CONNECTION=sync`: efek samping
  Observer/Event/Listener/Job akan langsung jalan synchronous saat
  `factory()->create()`. Kalau test tidak sedang menguji alur itu, isolasi
  dengan `Event::fake([...])` di `setUp()`.
- **Query rentang tanggal WAJIB pakai `whereDate()`**, bukan
  `whereBetween()` dengan string tanggal polos. Kolom ber-cast `'date'` di
  SQLite (dipakai testing) diserialisasi sebagai `'YYYY-MM-DD 00:00:00'`,
  dan `whereBetween` membandingkan secara leksikografis — batas atas
  string pendek `'2026-07-11'` dianggap lebih kecil dari
  `'2026-07-11 00:00:00'`, sehingga baris pada tanggal batas atas salah
  ter-eksklusi. Tidak muncul di PostgreSQL (kolom `DATE` asli), hanya di
  SQLite — jadi wajib `whereDate()` supaya konsisten lintas driver.
- **Event Echo dengan `broadcastAs()` custom**: listener client (`Echo.
  private(...).listen(...)`) WAJIB pakai titik di depan nama event
  (`.oee.updated`, bukan `oee.updated`) kalau backend memakai
  `broadcastAs()` untuk override nama default namespaced. Tanpa titik,
  Echo salah expect prefix namespace PHP dan tidak pernah menerima event.
- **Env var fallback di JavaScript**: `??` (nullish coalescing) TIDAK
  menangkap string kosong (`""`), hanya `null`/`undefined`. Kalau ada
  kemungkinan env var ter-resolve jadi string kosong (misal sintaks
  interpolasi `${VAR}` di `.env` yang gagal expand), pakai helper eksplisit
  yang cek ketiga kondisi (`undefined`/`null`/`""`), bukan cuma `??`.
- **Verifikasi "selesai" harus end-to-end**: unit/feature test PASS tidak
  menjamin frontend benar-benar bisa diakses di browser. Selalu build +
  buka browser + cek Network/Console tab sebelum menandai sesuatu
  "SELESAI & teruji" di dokumen ini.

---

## Roadmap per Phase

### Phase 1 — Foundation (Week 1–2) ✅ SELESAI (kecuali item bertanda)
- [x] Laravel scaffolding + Breeze
- [x] Inertia + Vue 3 (dibangun ulang dari nol di sesi 2026-07-19, sebelumnya tidak pernah ter-install — lihat § Koreksi Penting)
- [x] Semua migrations sekaligus
- [x] Models + relationships + factories
- [ ] Master data CRUD: WorkCenter, Product, Material (belum ada UI/Controller)
- [ ] BOM editor + Routing sequence editor
- [x] WorkOrder CRUD + generate wo_operations dari routing

### Phase 2 — Engine 1: Scheduler (Week 3–4) ✅ SELESAI (backend + frontend, terverifikasi browser)
- [x] SchedulingAlgorithmInterface + 4 implementasi (SPT, EDD, CR, FIFO)
- [x] JobShopSchedulerService::run() dan compareAll()
- [x] GanttBuilderService → D3-ready JSON
- [x] GanttChart.vue interaktif + Compare.vue — terverifikasi render + toggle algoritma di browser
- [x] ScheduleApplierService
- [x] Route halaman (`schedules.run`, `schedules.compare-all`, `schedules.show`) — sebelumnya hilang, sudah ditambahkan
- [ ] `ScheduleController::show()` masih closure inline, sebaiknya dipindah jadi method controller

### Phase 3 — Engine 2: OEE (Week 5–6) ✅ SELESAI (backend + frontend, terverifikasi browser)
- [x] OeeCalculatorService (bcmath) — compute, trendData, benchmarkVsWorldClass
- [x] DowntimeAnalysisService — paretoDowntime
- [x] ProductionLogObserver + RecalculateOeeJob + broadcast infrastructure
- [x] WorkCenterPolicy + routes/channels.php
- [x] ProductionLogController + DowntimeController + Form Requests
- [x] ProductionLogPolicy
- [x] Halaman Vue ProductionLogs/{Index,Create,Show,Edit}.vue — terverifikasi E2E
- [x] OeeGauge.vue, ParetoChart.vue, OEE/Dashboard.vue — terverifikasi render + data nyata di browser
- [x] OeeController (dashboard/pareto/trend/benchmark/latest-snapshot)
- [ ] Soketi benar-benar dijalankan & dites end-to-end (masih driver `log`; komponen sudah siap pakai)
- [ ] Investigasi kenapa `oee_snapshots` kosong untuk data historis (lihat § Utang Teknis)

### Phase 4 — Engine 3: Inventory (Week 7–8) 🔄 SEDANG JALAN
- [x] EoqCalculatorService (bcmath) — computeEoq, computeSafetyStock, computeRop, computeTotalAnnualCost, computeAndSave
- [x] Unit test EoqCalculatorService (5 test PASS)
- [ ] MrpService (BOM explosion, period-by-period netting)
- [ ] RunMrpJob + CheckReorderAlertsJob (daily scheduler)
- [ ] RopGauge.vue, MrpGrid.vue, AlertBanner.vue
- [ ] Feature test EoqCalculatorService::computeAndSave() (butuh RefreshDatabase)

### Phase 5 — Integration & Polish (Week 9–10) — belum dimulai
- [ ] Dashboard KPI lintas 3 engine
- [ ] Export PDF & Excel per engine (`barryvdh/laravel-dompdf`, `maatwebsite/excel` belum di-install)
- [ ] Master data CRUD (WorkCenter, Product, Material) + BOM/Routing editor
- [ ] Full test suite + canonical seeder review

---

## Urutan Kerja Per Sesi

1. Update `Current Build Status` di file ini
2. Baca docs yang relevan (tabel di § Documentation) — **cross-check dengan kode nyata kalau ragu**, docs kadang tidak sinkron dengan implementasi
3. migration → model → factory → service → controller → Vue page
4. Unit test setiap Service sebelum lanjut
5. **Verifikasi end-to-end di browser** untuk apapun yang menyentuh frontend/routing — jangan cuma andalkan `php artisan test`
6. `php artisan test` (full suite) sebelum selesai sesi — pastikan tidak ada regresi
7. Catat temuan/bug/utang teknis baru di § Utang Teknis, bukan cuma dilupakan setelah diperbaiki