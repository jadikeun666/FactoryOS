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
| Queue        | Laravel Queue, driver `database`. **Worker TIDAK permanen** — harus manual `php artisan queue:work`, lihat § Utang Teknis |
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

## ⚠️ Koreksi Penting (2026-07-20 / 2026-07-22)

Sesi ini menemukan beberapa bug/gap tambahan yang sebelumnya tidak
terdeteksi meski test suite hijau — konsisten dengan pelajaran di atas:
"unit/feature test PASS" tidak menjamin pipeline end-to-end (queue,
event dispatch, frontend reactivity) benar-benar bekerja.

1. **`oee_snapshots` kosong bukan karena bug kode** — root cause murni
   operasional: tidak pernah ada queue worker yang jalan untuk FactoryOS.
   Proses `queue:work` yang terlihat jalan di `ps aux` ternyata milik
   project lain (`geolevel/backend/laravel`). Backlog 16 job diproses via
   `queue:work --stop-when-empty`; beberapa gagal karena merujuk
   `ProductionLog` yang sudah terhapus dari seeding/testing berulang
   sebelumnya (bukan bug, `failed_jobs` dibersihkan via `queue:flush`).
2. **Bug ditemukan & diperbaiki**: `OeeGauge.vue` — watcher
   `props.initialSnapshot` punya guard `if (val) snapshot.value = val`
   yang mencegah reset ke `null` saat ganti ke work center tanpa data.
   Akibatnya gauge menampilkan data basi dari mesin sebelumnya, padahal
   3 komponen lain (benchmark/trend/pareto) sudah benar. Fix: hapus
   guard, selalu sinkronkan `snapshot.value = val` apa pun isinya.
3. **`app/Events/ScheduleCreated.php` didokumentasikan di
   `docs/architecture.md` tapi TIDAK PERNAH benar-benar dibuat** —
   diverifikasi via `find app/Events` (kosong) sebelum sesi ini. Dibangun
   dari nol sesi ini bersama `TriggerMrpRunListener`.
4. **Bug ditemukan & diperbaiki**: model `Inventory` tidak override
   `protected $table`, sehingga Eloquent menebak nama tabel plural
   `inventories` — padahal migration nyata membuat tabel bernama
   `inventory` (singular). Ditemukan saat menulis `MrpServiceTest`
   (query gagal "no such table: inventories"). Ini bug laten yang akan
   berdampak ke runtime, bukan cuma test — sudah diperbaiki dengan
   `protected $table = 'inventory';` eksplisit.
5. **Bug ditemukan, BELUM diperbaiki** (di luar scope sesi ini):
   `resources/js/Pages/Schedules/Show.vue` punya default prop
   `compareUrl: '/schedules/compare'` — path ini tidak punya route
   terdaftar apa pun (yang ada hanya `POST /schedules/compare-all` untuk
   fetch JSON). Klik tombol "↺ Bandingkan Ulang" menghasilkan 500 error
   (`invalid input syntax for type bigint: "compare"`) karena Laravel
   jatuh ke wildcard `GET /schedules/{schedule}` dengan
   `schedule="compare"`. Route statis vs wildcard ordering di
   `routes/web.php` SUDAH benar (statis sebelum wildcard) — root cause
   murni: tidak ada route `GET` untuk halaman Compare sama sekali.

---

## Production Environment

| Item             | Value                                            |
| ---------------- | ------------------------------------------------ |
| OS               | Ubuntu 24.04 LTS via WSL2                        |
| URL (dev)        | http://127.0.0.1:8000 (via `php artisan serve`)  |
| Project path     | `~/workspace/factoryos/laravel`                  |
| Queue workers    | **Tidak permanen** — jalankan manual `php artisan queue:work database` di terminal terpisah setiap kali butuh job diproses. Belum ada supervisor/systemd. |
| WebSocket server | Soketi (terpasang di sisi client, belum dijalankan — `BROADCAST_CONNECTION=log`) |

### Commands

```bash
npm run build
npm run dev
php artisan serve
php artisan test
php artisan migrate
php artisan tinker
php artisan queue:work database        # WAJIB dijalankan manual agar job (OEE, MRP) diproses
php artisan queue:work database --once # proses satu job saja
php artisan queue:failed               # cek job yang gagal
php artisan queue:flush                # hapus semua failed_jobs
```

Perintah lama yang tercatat di versi dokumen sebelumnya
(`sudo supervisorctl`, `http://factoryos.local`, `npx soketi start`)
**belum terverifikasi ada/jalan di environment ini** — jangan asumsikan
tersedia tanpa cek dulu.

**Catatan penting (2026-07-20)**: kalau `ps aux | grep queue:work`
menunjukkan proses jalan, VERIFIKASI dulu working directory proses
tersebut — bisa jadi milik project lain di mesin yang sama (kejadian
nyata: proses `queue:work` yang terlihat jalan ternyata milik
`geolevel/backend/laravel`, bukan FactoryOS).

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
- `JobShopSchedulerService` (run, compareAll, computeMetrics) — precedence antar-operasi, kontensi mesin, dan metrik (makespan, tardiness, flow time) tervalidasi manual terhadap walkthrough di `docs/scheduling.md`. `run()` mengembalikan instance `Schedule` (dikonfirmasi ulang sesi 2026-07-20 saat integrasi dengan `ScheduleCreated` event)
- `SchedulingException` — guard circular dependency / data tidak konsisten
- `WoOperationGeneratorService` — generate `wo_operations` dari routing produk, dengan idempotent-guard dan opsi force-regenerate
- `WorkOrderOperationGenerationException`, `WorkOrderStatusException`
- `WorkOrderStatusService` — validasi transisi status WO (draft→scheduled→in_progress→done/late) dan guard penghapusan (FR-02)
- `WorkOrderController` (thin controller) — CRUD, generate operations saat store, transisi status, regenerate operations manual
- `StoreWorkOrderRequest`, `UpdateWorkOrderRequest`, `UpdateWorkOrderStatusRequest`
- `WorkOrderPolicy` — update/delete hanya creator atau admin
- Route `work-orders.*` ter-register di `routes/web.php`
- `GanttBuilderService` (Schedule → JSON D3.js sesuai `docs/gantt.md`, termasuk `is_late` per WO & per assignment)
- `ScheduleController` — `run`, `compareAll`, `ganttData`, `apply` (semua thin, delegasi penuh ke Service). **Baru (2026-07-20)**: `run()` men-dispatch `ScheduleCreated::dispatch($schedule)` setelah Schedule berhasil dibuat, memicu pipeline MRP otomatis (lihat § Engine 3)
- Endpoint `GET /api/schedules/{schedule}/gantt-data` — **terverifikasi jalan nyata di browser** (lihat di atas)
- `ScheduleApplierService` — terapkan Schedule terpilih ke `wo_operations` (hanya operasi `pending`) + transisi status WO via `WorkOrderStatusService`
- `ScheduleApplyException`, `ApplyScheduleRequest`
- `GanttChart.vue` — Gantt interaktif berbasis D3 (toggle algoritma, tooltip, due-date line, zoom, klik-highlight WO) — **terverifikasi render di browser**
- `KpiCard.vue` — kartu metrik reusable dengan animasi count-up
- `Schedules/Compare.vue` — halaman perbandingan 4 algoritma + tombol "Terapkan Jadwal". **Bug ditemukan & diperbaiki (sesi lalu)**: memakai `target.schedule_id` padahal `Schedule` model attribute-nya `id` (bukan `schedule_id`) — sudah diperbaiki jadi `target.id`.
- `Schedules/Show.vue` — halaman detail satu schedule, membungkus `GanttChart.vue` — **terverifikasi render di browser**. **Bug BARU ditemukan, BELUM diperbaiki (2026-07-20)**: default prop `compareUrl: '/schedules/compare'` mengarah ke route yang tidak ada, tombol "↺ Bandingkan Ulang" 500 error. Lihat § Utang Teknis.
- 23 test PASS (unit algoritma, feature `JobShopSchedulerService`, unit `GanttBuilderService`, feature `ScheduleApplierService`)

**Engine 2 — OEE & Downtime Analytics (backend DAN frontend SELESAI & teruji end-to-end, termasuk pipeline queue nyata sesi 2026-07-20)**

*Backend:*
- `OeeCalculatorService` — Availability, Performance (cap 1.0), Quality, OEE sesuai ISO 22400, bcmath scale 6. bcmath native selalu truncate — helper `round()` manual (round-half-up) dan `roundSigned()` (untuk nilai negatif, dipakai di `benchmarkVsWorldClass()`) memastikan hasil match kalkulasi manual matematis
- `OeeCalculatorService::trendData()` — rata-rata OEE harian per mesin (multi-shift per tanggal), `INTERNAL_SCALE=12` sebelum round ke `SCALE=6`, `whereDate()` untuk filter rentang tanggal
- `OeeCalculatorService::benchmarkVsWorldClass()` — gap actual vs target world class (OEE 85%, Availability 90%, Performance 95%, Quality 99.99%), helper `roundSigned()` (round half-up aware nilai negatif — dijadikan pola rujukan untuk `MrpService::roundSigned()` di Engine 3, lihat di bawah)
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
- `OeeController` — `dashboard()`, `pareto()`, `trend()`, `benchmark()`, `latestSnapshotWithBenchmark()`. Pola validasi inline `$request->validate()` (bukan Form Request terpisah), mengikuti pola nyata `ScheduleController`
  - `latestSnapshotWithBenchmark()` ditambahkan supaya benchmark card di `OEE/Dashboard.vue` akurat saat ganti mesin (awalnya di-derive dari titik trend terakhir yang cuma rata-rata harian, tidak presisi untuk benchmark per-shift)
- Routes: `oee.dashboard` (web.php); `oee.pareto`, `oee.trend`, `oee.benchmark`, `oee.latest-snapshot` (api.php, grup `auth:sanctum`)
- 56 test PASS (Engine 2 backend): unit `OeeCalculatorServiceTest` (5), unit `OeeCalculatorServiceTrendAndBenchmarkTest` (6), unit `DowntimeAnalysisServiceTest` (4), feature `ProductionLogObserverTest` (4), feature `RecalculateOeeJobTest` (1), unit `WorkCenterPolicyTest` (3), unit `OeeUpdatedTest` (3), feature `ProductionLogControllerTest` (8), feature `DowntimeControllerTest` (5)

*Frontend (SELESAI & teruji end-to-end di browser, termasuk verifikasi pipeline queue nyata sesi 2026-07-20):*
- `ProductionLogs/{Index,Create,Show,Edit}.vue` — CRUD lengkap + downtime events. Terverifikasi via skrip E2E Playwright ad-hoc (`e2e-production-logs.mjs` di root project, tidak permanen, boleh dihapus kapan saja): Index/Create/Show OK, Edit menghasilkan 403 yang **benar sesuai desain** immutability (log `is_validated=true` atau bukan creator/admin) — bukan bug
- `OeeGauge.vue` — gauge arc SVG + sub-metrics bar (Availability/Performance/Quality), live update via Laravel Echo (channel `work-center.{id}`, event `.oee.updated` — **titik di depan wajib** karena `broadcastAs()` custom name, bukan default namespaced Laravel). **Bug ditemukan & diperbaiki (2026-07-20)**: watcher `props.initialSnapshot` sebelumnya punya guard `if (val) snapshot.value = val` yang mencegah reset ke `null` saat ganti ke work center tanpa data (gauge tetap menampilkan data basi mesin sebelumnya). Fix: hapus guard, watcher sekarang selalu `snapshot.value = val` apa pun isinya. Terverifikasi ulang di browser: 3 work center (2 dengan data, 1 tanpa) semua menampilkan state yang benar setelah fix.
- `resources/js/echo.js` — konfigurasi Echo untuk Soketi. **Bug ditemukan & diperbaiki (sesi lalu)**: fallback env var awalnya pakai `??` (nullish coalescing) yang TIDAK menangkap string kosong `""` (hanya `null`/`undefined`) — kalau `.env` pakai sintaks interpolasi `${VAR}` yang gagal resolve jadi string kosong, Echo diam-diam mencoba konek ke domain `pusher.com` asli. Diperbaiki dengan helper `envOrDefault()` + guard: kalau `VITE_PUSHER_APP_KEY` kosong, Echo **tidak diinisialisasi sama sekali** (window.Echo tetap undefined, komponen tampil "Offline" dengan sengaja)
- `ParetoChart.vue` — bar chart + garis kumulatif + garis threshold 80%, fetch via `/api/oee/pareto`, filter tanggal reaktif
- `OEE/Dashboard.vue` — gabungan `OeeGauge` + benchmark card + trend chart (4 garis: OEE/Availability/Performance/Quality, dengan circle marker per titik supaya data 1 titik tetap terlihat — d3 `<path>` butuh minimal 2 titik untuk tergambar) + `ParetoChart`. Ganti dropdown mesin memicu fetch ulang trend + snapshot + benchmark. **Logic reset state di komponen ini SUDAH benar** (dipakai sebagai rujukan pola yang benar saat diagnosis bug `OeeGauge.vue` di atas)
- Soketi masih **belum dijalankan nyata** (`BROADCAST_CONNECTION=log`) — semua komponen Echo sudah siap pakai, tinggal aktivasi di sesi lain tanpa perlu ubah kode Vue

**Pipeline OEE end-to-end — root cause `oee_snapshots` kosong ditemukan & diperbaiki (2026-07-20)**
- **Root cause**: bukan bug kode. Tidak pernah ada queue worker yang benar-benar jalan untuk FactoryOS — proses `queue:work` yang terlihat di `ps aux` ternyata milik project lain (`geolevel`). Job `RecalculateOeeJob` menumpuk di tabel `jobs` tanpa pernah diproses sejak awal.
- Backlog diproses via `php artisan queue:work database --stop-when-empty`. Beberapa job gagal (`ModelNotFoundException` untuk `ProductionLog` yang sudah terhapus dari seeding/testing berulang sebelumnya) — bukan bug, `failed_jobs` dibersihkan via `queue:flush`.
- Hasil akhir tervalidasi ganda: (1) query `oee_snapshots` cocok persis dengan contoh manual `docs/oee-formulas.md` (Availability=0.875000, Performance=0.904762, Quality=0.973684, OEE=0.770833) untuk log yang sesuai; (2) verifikasi visual browser untuk 3 work center berbeda (2 dengan data historis, 1 tanpa) menampilkan state yang benar setelah fix bug `OeeGauge.vue`.
- **Utang teknis baru**: queue worker masih harus dijalankan manual, belum ada supervisor/systemd permanen (lihat § Utang Teknis).

**Engine 3 — Inventory Optimizer (EOQ selesai sesi 2026-07-19; MRP backend selesai & terverifikasi end-to-end sesi 2026-07-20/22)**
- `EoqCalculatorService` (`app/Services/Inventory/`) — `computeEoq()`, `computeSafetyStock()`, `computeRop()`, `computeTotalAnnualCost()`, `computeAndSave()`. bcmath scale 6, `INTERNAL_SCALE = SCALE + 4`, `bcSqrt()` Newton-Raphson dengan guard `n=0` (hasil 0, bukan div-by-zero), `round()` half-up manual (pola identik `OeeCalculatorService`/`DowntimeAnalysisService`). **FINAL, tidak diubah sesi 2026-07-20.**
- 5 test PASS (`EoqCalculatorServiceTest`): EOQ (268.328157 — beda dari contoh docs 268.3281 karena docs pakai truncate 4 desimal sedangkan service ini round 6 desimal, keduanya benar untuk metode masing-masing), EOQ guard `demand=0`, Safety Stock, ROP, Total Annual Cost (validasi properti EOQ: ordering cost = holding cost di titik optimal)
- Test pakai `new InventoryParam([...])` langsung (bukan factory/DB) karena method yang diuji murni baca attribute in-memory. `computeAndSave()` **belum ada test** (butuh `RefreshDatabase`, feature test territory)

- **`app/Events/ScheduleCreated.php` (baru, 2026-07-20)** — sebelumnya HANYA didokumentasikan di `docs/architecture.md`, tidak pernah benar-benar dibuat (diverifikasi `find app/Events` kosong sebelum sesi ini). Di-dispatch dari `ScheduleController::run()` setelah `Schedule` berhasil dibuat. Listener lain yang disebut di docs (`LogScheduleActivity`) SENGAJA tidak dibuat — di luar scope sesi ini.
- **`app/Listeners/TriggerMrpRunListener.php` (baru)** — handle `ScheduleCreated`, dispatch `RunMrpJob`. Didaftarkan di `AppServiceProvider::boot()`.
- **`MrpService` (`app/Services/Inventory/MrpService.php`, baru)** — `run(int $scheduleId): MrpRun`, `explodeBom(WorkOrder $wo, Carbon $dueDate): array`, `computeRequirements(Material $material, array $grossReqs, Carbon $from): array`, `checkReorderAlerts(): Collection`. bcmath scale 6, `INTERNAL_SCALE = SCALE + 4`, `round()`/`roundSigned()` disalin pola persis dari `OeeCalculatorService` (deteksi negatif via `str_starts_with($number, '-')`, bukan `bccomp`, lalu delegasi ke `round()` untuk nilai absolut).
  - **Catatan skema penting**: tabel `mrp_requirements` hanya punya satu kolom tanggal (`period_date`), TIDAK ADA kolom terpisah untuk "tanggal rilis order" (`period_date - lead_time_days`, disebut di `docs/inventory.md` § backward scheduling). `planned_order_release` disimpan pada baris `period_date` yang sama dengan periode kebutuhan (need-date). Tanggal rilis tetap dihitung dan dikembalikan sebagai key `release_date` di return value `computeRequirements()` untuk keperluan lain (alert/logging), TIDAK dipaksakan ke migration yang tidak diubah.
  - **Catatan `scheduled_receipts`**: diimplementasikan APA ADANYA sesuai formula `docs/inventory.md` — query nyata ke `inventory_transactions` (`type='in'`, `whereDate()` per periode). Karena modul Purchase Order eksplisit di luar scope v1 (`prd.md`), hasilnya secara alami akan `0` untuk kebanyakan periode di v1 ini — TIDAK di-hardcode, murni konsekuensi belum ada data pengisi.
  - Periode yang dihitung di `computeRequirements()` adalah tanggal-tanggal yang benar-benar punya gross requirement (bukan grid kalender tetap t=1..t=N seperti ilustrasi walkthrough di docs — itu cuma contoh, bukan spek wajib).
  - `roundUpToEoq()`: fallback ke net requirement itu sendiri (tanpa pembulatan) jika material tidak punya `InventoryParam` atau EOQ tidak valid — lebih aman daripada gagal total untuk data yang belum lengkap.
- **`app/Jobs/RunMrpJob.php` (baru)** — pola identik `RecalculateOeeJob`: `tries=3`, `backoff=10`, service di-inject via method `handle()`, error di-log di `failed()`.
- **`app/Jobs/CheckReorderAlertsJob.php` (baru)** — pola sama. **BELUM dijadwalkan via Laravel Scheduler** (`dailyAt('06:00')` disebut di docs) — sengaja tidak diaktifkan sesi ini karena cron/Supervisor belum diverifikasi ada di environment ini (lihat § Utang Teknis). Testing dilakukan manual via tinker + `queue:work --once`.
- **Bug ditemukan & diperbaiki**: model `Inventory` tidak punya `protected $table` override, Eloquent menebak `inventories` (plural) padahal migration nyata membuat tabel `inventory` (singular). Ditemukan saat `MrpServiceTest` gagal dengan "no such table: inventories". Fix: tambah `protected $table = 'inventory';` eksplisit.
- **Verifikasi end-to-end nyata (bukan cuma unit test)**: `ScheduleController::run()` dipanggil (via tinker karena tombol UI compare rusak, lihat § Utang Teknis) → `ScheduleCreated::dispatch()` → `TriggerMrpRunListener` → `RunMrpJob` diproses via `queue:work` (RUNNING → DONE, terlihat di log worker) → `MrpRun` + 5 `MrpRequirement` (satu per material dari BOM 3 Product yang ada) tersimpan di PostgreSQL dengan angka yang matematis benar (net requirement, EOQ rounding, projected on-hand negatif untuk defisit, semuanya cocok formula `docs/inventory.md`).
- **Verifikasi `checkReorderAlerts()`**: material dengan `qty_on_hand=20 < ROP=38.5477` (dihitung nyata via `EoqCalculatorService::computeAndSave()`, bukan hardcode) memicu 1 alert status `open`. Run kedua menghasilkan 0 alert baru — idempotency guard (`whereStatus('open')->exists()`) terverifikasi bekerja.
- 3 test PASS (`MrpServiceTest`, 30 assertions) — tervalidasi terhadap 2 skenario manual "Contoh MRP Grid" di `docs/inventory.md` (Besi Plat 2mm, Lead Time 3 hari, EOQ 100 lembar): on-hand 50 + scheduled receipt cukup → tidak ada Net Requirement; on-hand 10 tanpa SR → Net Requirement 20 dibulatkan ke EOQ 100, release_date mundur 3 hari, projected on-hand defisit tersimpan sebagai -20 (bukan di-clamp ke 0). Plus test `explodeBom()` terpisah.
  - Catatan teknis test: pakai `RefreshDatabase` (bukan in-memory murni seperti `EoqCalculatorServiceTest`) karena butuh relasi Eloquent nyata. `InventoryTransaction::create()` biasa TIDAK BISA set `created_at` custom (kolom ini sengaja tidak ada di `$fillable` karena tabel immutable) — harus pakai `forceFill()` + `save()`, pola yang sudah didokumentasikan di § Catatan Teknis Penting.

**Total: 99 test PASS (295 assertions), full suite, tidak ada regresi ke Engine 1/2 dari perubahan sesi 2026-07-20/22 (96→99 test, 265→295 assertions, murni penambahan `MrpServiceTest`)**

### 🔄 In Progress
- (belum ada — siap mulai task baru)

### ⏳ Not Started
- Halaman Vue/Inertia `WorkOrders/{Index,Create,Show,Edit}.vue` — controller sudah render `Inertia::render(...)` tapi file `.vue` belum ada
- Master data CRUD: WorkCenter, Product, Material (belum ada UI/Controller)
- BOM editor + Routing sequence editor
- Soketi belum benar-benar dijalankan end-to-end (`BROADCAST_CONNECTION` masih `log`; isi `VITE_PUSHER_*` di `.env` + `npx soketi start` untuk aktivasi — `OeeGauge.vue` akan otomatis tersambung tanpa ubah kode)
- `ScheduleController::show()` masih closure inline di `routes/web.php` — sebaiknya dipindah jadi method controller yang sesungguhnya
- **`MrpController`** — belum dibuat. MRP saat ini hanya bisa dipicu otomatis via `ScheduleCreated` (setelah `schedules.run`) atau manual via tinker. Perlu endpoint untuk lihat hasil MRP grid / trigger ulang dari UI.
- **Frontend MRP**: `RopGauge.vue`, `MrpGrid.vue`, `AlertBanner.vue` — belum dimulai sama sekali
- `ExportService` (PDF/Excel per engine) — `barryvdh/laravel-dompdf` dan `maatwebsite/excel` belum di-`composer require`
- Dashboard KPI lintas 3 engine
- Feature test untuk `OeeController` dan `EoqCalculatorService::computeAndSave()`
- Unit/feature test terpisah untuk `MrpService::checkReorderAlerts()` (baru diverifikasi manual via tinker, belum ada assertion otomatis)
- Fix bug `Schedules/Show.vue` `compareUrl` (lihat § Utang Teknis)

---

## ⚠️ Utang Teknis / Perlu Investigasi

1. **Queue worker FactoryOS tidak permanen** — harus manual `php artisan queue:work database` di terminal terpisah. Belum ada supervisor/systemd config terverifikasi ada di environment ini. **PENTING**: sebelum asumsi ada worker jalan, selalu cek working directory proses via `ps aux`, karena proses `queue:work` bisa jadi milik project lain di mesin yang sama (kejadian nyata sesi 2026-07-20).
2. **`Schedules/Show.vue` bug (BELUM diperbaiki)**: default prop `compareUrl: '/schedules/compare'` tidak punya route terdaftar — klik tombol "↺ Bandingkan Ulang" menghasilkan 500 error. Route statis vs wildcard ordering di `routes/web.php` sudah benar; root cause murni tidak ada route `GET` untuk halaman Compare. Perlu diputuskan: buat route baru, atau ubah `compareUrl` agar mengarah ke route yang sudah ada.
3. **`MrpController` belum ada** — tidak diminta eksplisit di sesi 2026-07-20, tapi dibutuhkan untuk UI MRP berikutnya (lihat brief pola `ScheduleController`/`OeeController`: thin, validasi inline, bukan Form Request terpisah).
4. **`CheckReorderAlertsJob` belum dijadwalkan otomatis** — Laravel Scheduler (`dailyAt('06:00')`) sengaja tidak diaktifkan karena cron/Supervisor belum diverifikasi ada. Job sudah dibuat & diuji manual.
5. **Data seeder Engine 3 minim** — hanya 1 material (`Besi Plat 2mm voluptates`, id=4) yang punya `Inventory`+`InventoryParam` terisi (diisi manual sesi ini untuk keperluan testing). Material lain dari BOM akan selalu menghasilkan `net_requirement = gross_requirement` penuh tanpa pembulatan EOQ karena tidak ada data pembanding — bukan bug, tapi perlu data lebih lengkap untuk demo/testing selanjutnya.
6. **`oee_snapshots` historical backfill** — sudah dilakukan untuk backlog yang ada (16 job, beberapa gagal karena data stale/sudah dihapus — sudah dibersihkan). Kalau ada `ProductionLog` baru ditambahkan di masa depan tanpa queue worker jalan, masalah yang sama akan berulang — **selalu pastikan `queue:work` jalan** saat testing fitur yang melibatkan `ProductionLog`/`Schedule` baru.
7. `e2e-production-logs.mjs` di root project adalah skrip diagnostik ad-hoc (Playwright), bukan bagian dari test suite permanen — aman dihapus, atau bisa dipertahankan sebagai referensi pola E2E untuk halaman lain.

---

## Koreksi Dokumen (formula)

`docs/oee-formulas.md` dan `docs/engineering-rules.md` sebelumnya menyatakan
hasil OEE contoh manual = 0.771099. **Ini salah hitung di dokumen aslinya.**
Hasil yang benar secara matematis: 0.875000 × 0.904762 × 0.973684 = **0.770833**.
Sudah dikoreksi di kedua file docs tersebut dan di semua test terkait.
Nilai ini juga terverifikasi ulang secara nyata di `oee_snapshots` sesi
2026-07-20 (bukan cuma di unit test) — lihat § Pipeline OEE end-to-end.

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
- Engine 1 → `ScheduleCreated` event → Engine 3 (`TriggerMrpRunListener` → `RunMrpJob`) hitung kebutuhan material — **terhubung nyata sejak sesi 2026-07-20**, bukan cuma didokumentasikan
- Engine 3 → safety stock info → Engine 1 tahu material tersedia (belum ada UI untuk ini)

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
`RunScheduleRequest`, atau keberadaan `ScheduleCreated` sebelum sesi
2026-07-20) ternyata tidak sesuai implementasi nyata — controller yang
sudah ada pakai validasi inline `$request->validate()`, bukan Form
Request terpisah. Kalau ragu, cek kode controller yang sudah ada dulu
sebelum asumsi dari docs. **Selalu verifikasi keberadaan file dengan
`find`/`cat`/`ls` sebelum berasumsi sesuatu sudah ada, sekalipun
didokumentasikan.**

---

## Main Services

| Service                   | Tanggung Jawab                                     | Status       |
| ------------------------- | --------------------------------------------------- | ------------ |
| `JobShopSchedulerService` | Jalankan 4 dispatching rules, simpan schedule       | ✅ Selesai   |
| `GanttBuilderService`     | Transform assignments → D3.js-ready dataset         | ✅ Selesai   |
| `ScheduleApplierService`  | Terapkan schedule terpilih ke wo_operations         | ✅ Selesai   |
| `OeeCalculatorService`    | Hitung OEE, trend data, benchmark vs world class    | ✅ Selesai   |
| `DowntimeAnalysisService` | Pareto analysis downtime (agregat cross-log)        | ✅ Selesai   |
| `EoqCalculatorService`    | EOQ, Safety Stock, ROP, Total Annual Cost (bcmath)  | ✅ Selesai (final, jangan diubah) |
| `MrpService`              | MRP explosion: schedule → material requirements, reorder alerts | ✅ Selesai (backend, terverifikasi end-to-end) |
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
Tervalidasi ulang secara nyata di `oee_snapshots` sesi 2026-07-20.

**ENGINE 3 — INVENTORY**
```
EOQ          = √(2 × D × S / H)
Safety Stock = Z × σ_d × √(LT)
ROP          = (avg_daily_demand × LT) + Safety Stock
Net Req(t)   = max(0, GrossReq(t) - ProjOnHand(t-1) - ScheduledReceipts(t))
Planned Order Release = roundUpToEoq(Net Req(t)), disimpan di period_date
                         yang sama (need-date) -- lihat catatan skema di
                         § Engine 3 soal keterbatasan kolom mrp_requirements
```
Contoh manual tervalidasi (`EoqCalculatorServiceTest`):
D=1200, S=150000, H=5000 → EOQ=268.328157;
Z=1.6450, σ_d=3, LT=7 → Safety Stock=13.056783, ROP=83.056783
(dengan avg_daily_demand=10, yaitu annual_demand=3650).

Contoh manual tervalidasi (`MrpServiceTest`, sesuai docs/inventory.md §
Contoh MRP Grid — Besi Plat 2mm, LT=3 hari, EOQ=100):
on-hand=50 + SR=100 di t1 → tidak ada Net Requirement di periode manapun;
on-hand=10, GR=30 di t2 → NR=max(0,30-10-0)=20 → roundUpToEoq(20,100)=100,
release_date = t2 - 3 hari (mengindikasikan order sudah terlambat).

Tervalidasi ulang di data seeder nyata sesi 2026-07-22 (material id=4,
"Besi Plat 2mm voluptates"): EOQ dihitung otomatis via
`EoqCalculatorService::computeEoq()` (bukan hardcode) = 467.9744 dari
D=3650, S=150000, H=5000. ROP = 38.5477 (avg_daily=10 × LT=3 + safety
stock 8.5477). Reorder alert terpicu benar saat qty_on_hand=20 < ROP,
dan idempotency guard mencegah duplikasi alert pada run kedua.

---

## Catatan Teknis Penting (pelajaran dari sesi-sesi sebelumnya)

- **bcmath tidak pernah membulatkan**, selalu truncate. Untuk hasil yang
  perlu match kalkulasi manual matematis biasa (round half up), pakai
  helper `round()` manual seperti di `OeeCalculatorService`/
  `DowntimeAnalysisService`/`EoqCalculatorService`/`MrpService` — jangan
  asumsikan `bcdiv`/`bcmul` otomatis akurat ke digit terakhir.
- **Pola `round()`/`roundSigned()` baku di seluruh project** (dipakai
  identik di `OeeCalculatorService`, `EoqCalculatorService`,
  `MrpService`): `round()` menambahkan `0.5` pada digit ke-(scale+1)
  sebelum bcadd scale final (hanya untuk nilai non-negatif);
  `roundSigned()` deteksi tanda negatif via `str_starts_with($number,
  '-')` (BUKAN `bccomp`), lalu delegasikan ke `round()` untuk nilai
  absolutnya. Kalau menulis service kalkulasi baru, salin pola ini
  persis — jangan reimplementasi dari nol.
- **Laravel 12 tidak pakai `EventServiceProvider` bawaan** — event/listener
  diregister manual di `AppServiceProvider::boot()` via `Event::listen(...)`.
  Sesi 2026-07-20 menambahkan `ScheduleCreated`/`TriggerMrpRunListener` ke
  daftar ini.
- **Sebuah Event/Listener yang disebut di `docs/architecture.md` belum
  tentu benar-benar ada di kode** — selalu verifikasi dengan `find
  app/Events` / `find app/Listeners` sebelum berasumsi. Kejadian nyata:
  `ScheduleCreated` didokumentasikan sejak awal tapi baru benar-benar
  dibuat sesi 2026-07-20.
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
  bigint) di query. **CATATAN**: ordering di `routes/web.php` SUDAH benar
  sejak sesi 2026-07-19 — bug `Schedules/Show.vue` `compareUrl` (lihat §
  Utang Teknis) BUKAN disebabkan oleh ordering yang salah, melainkan
  route tujuan yang memang belum pernah didaftarkan sama sekali.
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
  `MrpService::getScheduledReceipts()` sudah mengikuti pola ini.
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
- **Watcher Vue pada prop yang bisa `null`**: JANGAN pakai guard seperti
  `if (val) target.value = val` kalau prop tsb memang bisa/harus mereset
  ke `null` (mis. snapshot kosong untuk entity tanpa data). Guard seperti
  itu membuat komponen tidak pernah ter-reset dan menampilkan data basi
  dari state sebelumnya. Kejadian nyata: `OeeGauge.vue` watcher
  `initialSnapshot` (sudah diperbaiki 2026-07-20). Selalu sinkronkan
  `target.value = val` tanpa syarat kalau parent memang bisa mengirim
  `null` sebagai state valid.
- **Model Eloquent WAJIB override `$table` eksplisit kalau nama tabel
  migration TIDAK mengikuti konvensi plural default Laravel** dari nama
  class-nya. Kejadian nyata: model `Inventory` (tanpa `$table`) menebak
  tabel `inventories`, padahal migration membuat tabel `inventory`
  (singular, sesuai `docs/database.md`). Tidak error sampai ada kode yang
  benar-benar query lewat model itu — periksa proaktif untuk model lain
  yang nama tabelnya tidak jelas-jelas mengikuti pluralization standar.
- **`$fillable` yang sengaja tidak menyertakan `created_at`** (pola untuk
  tabel immutable seperti `InventoryTransaction`, `Schedule`,
  `ScheduleAssignment`, `MrpRequirement`) berarti `Model::create([...
  'created_at' => $customDate])` akan DIAM-DIAM MENGABAIKAN nilai
  `created_at` custom tsb dan jatuh ke default migration (`useCurrent()`
  atau `now()`via Eloquent). Untuk test yang butuh timestamp custom pada
  tabel immutable, WAJIB pakai `forceFill([...])` + `save()`, bukan
  `create([...])` biasa — sudah didokumentasikan sebagai pola di
  `claude.md` versi lama (`forceFill()` + `save()` untuk non-fillable
  timestamp fields) tapi sempat terlewat saat menulis `MrpServiceTest`
  pertama kali sesi 2026-07-20, menyebabkan test gagal secara halus
  (bukan error, tapi assertion salah karena tanggal tidak sesuai
  ekspektasi).
- **Proses lain di `ps aux` dengan nama command yang sama (`queue:work`,
  dll.) bisa jadi milik project LAIN di mesin yang sama** — selalu
  verifikasi working directory/path proses sebelum mengasumsikan itu
  worker untuk project yang sedang dikerjakan.
- **Verifikasi "selesai" harus end-to-end**: unit/feature test PASS tidak
  menjamin frontend benar-benar bisa diakses di browser, ATAU bahwa
  pipeline queue/event benar-benar jalan di runtime nyata (bukan cuma
  disimulasikan test). Selalu build + buka browser + cek Network/Console
  tab + (untuk fitur yang melibatkan queue) jalankan `queue:work` nyata
  dan verifikasi data tersimpan di database sungguhan, sebelum menandai
  sesuatu "SELESAI & teruji" di dokumen ini.

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
- [x] JobShopSchedulerService::run() dan compareAll() — `run()` mengembalikan `Schedule`, dikonfirmasi ulang 2026-07-20
- [x] GanttBuilderService → D3-ready JSON
- [x] GanttChart.vue interaktif + Compare.vue — terverifikasi render + toggle algoritma di browser
- [x] ScheduleApplierService
- [x] Route halaman (`schedules.run`, `schedules.compare-all`, `schedules.show`) — sebelumnya hilang, sudah ditambahkan
- [x] `ScheduleController::run()` men-dispatch `ScheduleCreated` event (baru 2026-07-20) — menghubungkan Engine 1 ke Engine 3 secara otomatis
- [ ] `ScheduleController::show()` masih closure inline, sebaiknya dipindah jadi method controller
- [ ] **Bug baru**: `Schedules/Show.vue` tombol "↺ Bandingkan Ulang" 500 error (`compareUrl` mengarah ke route yang tidak ada) — lihat § Utang Teknis

### Phase 3 — Engine 2: OEE (Week 5–6) ✅ SELESAI (backend + frontend + pipeline queue nyata, semua terverifikasi end-to-end)
- [x] OeeCalculatorService (bcmath) — compute, trendData, benchmarkVsWorldClass
- [x] DowntimeAnalysisService — paretoDowntime
- [x] ProductionLogObserver + RecalculateOeeJob + broadcast infrastructure
- [x] WorkCenterPolicy + routes/channels.php
- [x] ProductionLogController + DowntimeController + Form Requests
- [x] ProductionLogPolicy
- [x] Halaman Vue ProductionLogs/{Index,Create,Show,Edit}.vue — terverifikasi E2E
- [x] OeeGauge.vue, ParetoChart.vue, OEE/Dashboard.vue — terverifikasi render + data nyata di browser
- [x] OeeController (dashboard/pareto/trend/benchmark/latest-snapshot)
- [x] **Pipeline queue OEE end-to-end terverifikasi nyata (2026-07-20)** — root cause `oee_snapshots` kosong ditemukan (queue worker tidak pernah jalan) & diperbaiki, backlog diproses, data cocok formula manual
- [x] **Bug `OeeGauge.vue` stale state diperbaiki (2026-07-20)** — watcher reset ke null sekarang bekerja benar
- [ ] Soketi benar-benar dijalankan & dites end-to-end (masih driver `log`; komponen sudah siap pakai)

### Phase 4 — Engine 3: Inventory (Week 7–8) 🔄 SEDANG JALAN
- [x] EoqCalculatorService (bcmath) — computeEoq, computeSafetyStock, computeRop, computeTotalAnnualCost, computeAndSave (FINAL, tidak diubah)
- [x] Unit test EoqCalculatorService (5 test PASS)
- [x] **MrpService (BOM explosion, period-by-period netting) — SELESAI & terverifikasi end-to-end (2026-07-20/22)**
- [x] **ScheduleCreated event + TriggerMrpRunListener — dibangun dari nol, sebelumnya cuma didokumentasikan**
- [x] **RunMrpJob + CheckReorderAlertsJob — dibuat, diverifikasi manual via tinker + queue:work**
- [x] **Unit test MrpService (3 PASS, 30 assertions) — tervalidasi terhadap contoh manual docs/inventory.md**
- [x] **Bug model Inventory (nama tabel salah) ditemukan & diperbaiki**
- [ ] `MrpController` — belum dibuat, tidak diminta eksplisit sesi ini
- [ ] RopGauge.vue, MrpGrid.vue, AlertBanner.vue — belum dimulai
- [ ] Feature test EoqCalculatorService::computeAndSave() (butuh RefreshDatabase)
- [ ] Unit/feature test terpisah untuk `MrpService::checkReorderAlerts()` (baru diverifikasi manual)
- [ ] Laravel Scheduler untuk `CheckReorderAlertsJob` (dailyAt 06:00) — sengaja belum diaktifkan, cron/Supervisor belum diverifikasi ada

### Phase 5 — Integration & Polish (Week 9–10) — belum dimulai
- [ ] Dashboard KPI lintas 3 engine
- [ ] Export PDF & Excel per engine (`barryvdh/laravel-dompdf`, `maatwebsite/excel` belum di-install)
- [ ] Master data CRUD (WorkCenter, Product, Material) + BOM/Routing editor
- [ ] Full test suite + canonical seeder review
- [ ] Data seeder Engine 3 lebih lengkap (saat ini hanya 1 material punya Inventory+InventoryParam)
- [ ] Queue worker permanen (supervisor/systemd) untuk FactoryOS
- [ ] Fix bug `Schedules/Show.vue` `compareUrl`

---

## Urutan Kerja Per Sesi

1. Update `Current Build Status` di file ini
2. Baca docs yang relevan (tabel di § Documentation) — **cross-check dengan kode nyata kalau ragu**, docs kadang tidak sinkron dengan implementasi. **Verifikasi keberadaan file/class dengan `find`/`cat` sebelum asumsi, sekalipun didokumentasikan** (kejadian nyata: `ScheduleCreated` event, model `Inventory` salah tabel — keduanya baru ketahuan saat benar-benar diverifikasi, bukan dari membaca docs).
3. **Sebelum menjalankan apa pun yang melibatkan queue (schedule run, production log baru, dll.), pastikan `php artisan queue:work database` sedang jalan di terminal terpisah** — dan verifikasi itu benar-benar proses untuk FactoryOS, bukan project lain (`ps aux` + cek working directory).
4. migration → model → factory → service → controller → Vue page
5. Unit test setiap Service sebelum lanjut
6. **Verifikasi end-to-end di browser DAN di database nyata** untuk apapun yang menyentuh frontend/routing/queue — jangan cuma andalkan `php artisan test`. Untuk fitur berbasis queue, jalankan job nyata via `queue:work` dan cek hasilnya di tabel database, bukan cuma di unit test dengan mock.
7. `php artisan test` (full suite) sebelum selesai sesi — pastikan tidak ada regresi
8. Catat temuan/bug/utang teknis baru di § Utang Teknis, bukan cuma dilupakan setelah diperbaiki