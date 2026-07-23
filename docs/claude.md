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
| Queue        | Laravel Queue, driver `database`. **Worker PERMANEN via Supervisor sejak sesi 2026-07-22** — lihat § Production Environment |
| Scheduler    | Laravel Scheduler, **aktif sejak sesi 2026-07-22** via Supervisor (`schedule:work`) — lihat § Production Environment |
| Precision    | PHP bcmath (semua kalkulasi kritis) |

> Auth: Breeze Blade stack — login/register adalah Blade biasa
> (`resources/js/app.js`, layout `layouts/app.blade.php`/`layouts/guest.blade.php`),
> sedangkan semua halaman lain menggunakan Inertia + Vue 3 (entry terpisah
> `resources/js/inertia-app.js`, root view `resources/views/app.blade.php`).
> Dua entry point ini SENGAJA terpisah — jangan digabung.
>
> Tidak ada paid AI API. Semua intelligence adalah algoritma deterministik murni.
>
> **Tidak ada folder `resources/js/Layouts/`** — semua halaman Inertia
> (Schedules, Mrp, WorkCenters, Materials, Products) bersifat standalone,
> tanpa layout wrapper bersama (nav/sidebar). Dikonfirmasi sesi 2026-07-23
> saat membangun halaman Mrp/Dashboard.vue.

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
5. **Bug ditemukan (2026-07-20), DIPERBAIKI sesi 2026-07-22**:
   `resources/js/Pages/Schedules/Show.vue` punya default prop
   `compareUrl: '/schedules/compare'` — path ini tidak punya route
   terdaftar apa pun. Lihat § Koreksi Penting (2026-07-22 lanjutan) di
   bawah untuk detail fix.

---

## ⚠️ Koreksi Penting (2026-07-22, sesi penutupan 6 utang teknis)

Sesi ini menyelesaikan 6 utang teknis yang tercatat dari sesi sebelumnya.
Detail per item:

1. **Fix bug `Schedules/Show.vue` "↺ Bandingkan Ulang" 500 error** —
   root cause: `compareAll()` di `ScheduleController` hanya mengembalikan
   JSON (dipakai untuk fetch API), dan **tidak ada satupun route GET yang
   merender halaman `Schedules/Compare.vue`** — bukan sekadar salah path
   seperti dugaan awal. Fix: tambah route baru `GET /schedules/compare`
   (`schedules.compare`) di `routes/web.php`, ditempatkan setelah route
   POST statis dan sebelum wildcard `/schedules/{schedule}` (konsisten
   dengan aturan ordering yang sudah ada). **Detail teknis penting**:
   `JobShopSchedulerService::compareAll()` mengembalikan array asosiatif
   `['spt' => Schedule, 'edd' => Schedule, ...]` — WAJIB dibungkus
   `array_values()` sebelum dikirim sebagai prop Inertia, kalau tidak
   Inertia akan serialize sebagai JSON object dan merusak semua method
   Array (`.map()`/`.find()`/`.sort()`) yang dipakai `Compare.vue`.
   Terverifikasi render 4 kartu algoritma di browser tanpa 500 error.
2. **`MrpController` dibuat** (`app/Http/Controllers/MrpController.php`)
   — thin, validasi inline (bukan Form Request terpisah, konsisten pola
   `ScheduleController`/`OeeController`), delegasi penuh ke `MrpService`
   (tidak diubah logic-nya). 3 endpoint JSON: `POST /mrp/run` (`mrp.run`),
   `GET /mrp/runs/{mrpRun}` (`mrp.runs.show`), `GET /mrp/alerts`
   (`mrp.alerts`, read-only, tidak memicu pembuatan alert baru). Route
   `/mrp/alerts` didaftarkan sebelum wildcard `/mrp/runs/{mrpRun}`
   mengikuti disiplin ordering yang sama. **(Method `dashboard()` untuk
   halaman Inertia ditambahkan sesi 2026-07-23, lihat § Koreksi Penting
   2026-07-23.)**
3. **Test `MrpService::checkReorderAlerts()` ditambahkan** ke
   `tests/Unit/Services/Inventory/MrpServiceTest.php` (file yang sudah
   ada, BUKAN file baru) — 3 test baru: (a) alert dibuat saat
   `qty_on_hand + qty_on_order <= rop`, (b) idempotency guard mencegah
   duplikasi alert saat status `open` masih ada, (c) `eoq_qty` pada alert
   mengambil dari `InventoryParam::eoq` yang tersimpan, bukan dihitung
   ulang. **Catatan teknis**: assertion nilai desimal harus cocok cast
   model `ReorderAlert` (`decimal:4`, BUKAN scale 6 seperti bcmath
   internal `MrpService`) — sempat gagal di percobaan pertama karena
   salah asumsi jumlah desimal, sudah diperbaiki.
4. **Queue worker permanen via Supervisor** — dikonfirmasi `supervisord`
   sudah terpasang (`/usr/bin/supervisord`) dan systemd aktif penuh di
   WSL2 ini (`systemctl status` sukses). Config baru:
   `/etc/supervisor/conf.d/factoryos-worker.conf`
   (`php artisan queue:work database --sleep=3 --tries=3 --backoff=10
   --max-time=3600`). **Konfirmasi ulang temuan sesi lalu**: Supervisor
   daemon yang sudah lama jalan di mesin ini ternyata HANYA mengelola
   proses `geolevel` (`geolevel-scheduler`, `geolevel-worker_00/01`) —
   `factoryos-worker` sekarang terdaftar terpisah dan diverifikasi lewat
   `ps aux` (path proses benar `/home/ciko/workspace/factoryos/laravel`)
   dan job nyata (`ScheduleCreated` → `RunMrpJob`) diproses otomatis
   tanpa perlu `queue:work` manual lagi.
5. **Seeder Engine 3 dilengkapi** — sebelumnya hanya 1 material (id=4)
   yang punya `Inventory`+`InventoryParam` (diisi manual). Sekarang
   `DatabaseSeeder::run()` Step 8 otomatis membuat `Inventory`+
   `InventoryParam` untuk **setiap material yang benar-benar dipakai di
   BOM** (bukan seluruh 10 material), dengan angka bervariasi
   (`annual_demand`, `ordering_cost`, `holding_cost` 20-30% x unit_cost,
   `lead_time_days`, `demand_std_dev` — semua acak dalam rentang
   realistis, bukan seragam), EOQ/SafetyStock/ROP dihitung nyata via
   `EoqCalculatorService::computeAndSave()` (bukan hardcode), dan
   `qty_on_hand` sengaja 30% kasus di bawah ROP supaya reorder alert
   punya skenario nyata untuk demo/testing. `firstOrCreate()` dipakai
   supaya idempotent. Diverifikasi via `migrate:fresh --seed`: 6 material
   (dari BOM 3 Product) mendapat data lengkap dan bervariasi (EOQ
   147–305, ROP 41–86 pada percobaan pertama).
6. **Laravel Scheduler untuk `CheckReorderAlertsJob` diaktifkan** —
   setelah diskusi eksplisit dengan user (sesuai batasan sesi:
   TIDAK ditulis kode apapun sebelum ada persetujuan), dipilih pendekatan
   **Supervisor + `schedule:work`** (bukan cron asli `crontab -e`),
   karena paling selaras `docs/architecture.md` (`dailyAt('06:00')` via
   Laravel Scheduler API, bukan cron langsung memanggil job) dan paling
   modern/presisi (proses tunggal yang "tidur" sampai menit berikutnya,
   bukan polling cron OS setiap menit). Didaftarkan di `routes/console.php`
   (Laravel 12 tidak punya `app/Console/Kernel.php` — `Schedule::job(new
   CheckReorderAlertsJob())->dailyAt('06:00')` ditaruh di
   `routes/console.php`). Dikelola via config Supervisor baru:
   `/etc/supervisor/conf.d/factoryos-scheduler.conf`
   (`php artisan schedule:work`, **`numprocs=1` WAJIB** — lebih dari 1
   instance akan menyebabkan job scheduled dieksekusi ganda).
   Terverifikasi via `php artisan schedule:list` (job terdaftar, next-run
   terhitung benar) dan `php artisan schedule:test` (RUNNING → DONE
   44.83ms, tanpa perlu menunggu jam 06:00 asli).

**Temuan tambahan (di luar 6 item wajib, ditemukan & diperbaiki sesi ini):**

7. **Bug seeder: `WorkOrder::factory()->count(15)->create()` tidak
   generate `wo_operations`** — ditemukan saat verifikasi end-to-end MRP
   dengan data seeder murni menghasilkan `MrpRun` dengan `requirements`
   KOSONG meski `Schedule` berhasil dibuat. Root cause: generate
   `wo_operations` dari routing produk terjadi di
   `WorkOrderController::store()` (controller layer), BUKAN otomatis
   dari factory — jadi WO hasil seeder tidak pernah benar-benar bisa
   dijadwalkan/di-MRP-kan sampai `wo_operations` dibuat manual. **Fix**:
   `DatabaseSeeder::run()` Step 7 sekarang memanggil
   `WoOperationGeneratorService::generate($workOrder)` (namespace benar:
   `App\Services\WorkOrder\WoOperationGeneratorService`, BUKAN
   `App\Services\Scheduling\...`) untuk setiap WO seeder. Diverifikasi:
   42 `wo_operations` tergenerate untuk 15 WO.
8. **Ketidaksesuaian versi OS**: `cat /etc/os-release` menunjukkan
   `VERSION_ID="26.04"` ("Resolute Raccoon"), BUKAN "24.04 LTS" seperti
   tercatat di dokumen ini dan `docs/prd.md` sejak awal. Murni koreksi
   catatan, bukan bug kode — lihat § Production Environment untuk versi
   yang sudah diperbarui.

**Full test suite akhir sesi 2026-07-22: 102 PASS, 303 assertions, tidak
ada regresi.**

---

## ⚠️ Koreksi Penting (2026-07-23, sesi Frontend MRP + Master Data CRUD)

Sesi ini mengerjakan 2 fitur baru dari daftar prioritas ROKC sesi lalu:
**Frontend MRP** (selesai penuh) dan **Master Data CRUD** (selesai penuh,
termasuk BOM & Routing editor). Berikut detail temuan/bug/keputusan
penting per bagian.

### Frontend MRP

1. **`InventoryController` baru dibuat** (`app/Http/Controllers/
   InventoryController.php`) — read-only, method `status()` mengembalikan
   `Material` + `Inventory` + `InventoryParam` join untuk **semua**
   material yang punya keduanya. Dibutuhkan karena `MrpController::
   alerts()` hanya mengembalikan material yang **sudah** di-flag alert,
   padahal FR-06 minta visual "on-hand vs safety stock vs ROP" untuk
   **semua** material — gap ini baru terlihat sekarang, bukan sebelumnya.
   Route: `GET /inventory/status` (`inventory.status`).
2. **`MrpController::dashboard()` method baru ditambahkan** (bukan
   controller terpisah) — `GET /mrp` (`mrp.dashboard`), merender
   `Pages/Mrp/Dashboard.vue`. `MrpService`/`EoqCalculatorService` TETAP
   tidak diubah (final).
3. **Komponen baru**: `AlertBanner.vue`, `RopGauge.vue`, `MrpGrid.vue`
   (`resources/js/Components/`), digabung di `Pages/Mrp/Dashboard.vue`.
   `MrpGrid.vue` pakai tabel native (bukan D3) — data MRP murni tabular
   (material × periode), beda dengan Gantt/Pareto yang spasial/temporal.
   Format grid: rowspan per material, 5 baris (GR/SR/POH/NR/POR) mengikuti
   contoh `docs/inventory.md § Contoh MRP Grid`, highlight kuning untuk
   NR>0, biru untuk POR>0.
4. **Bug ditemukan & diperbaiki**: tombol "Jalankan MRP Ulang" di
   `Mrp/Dashboard.vue` awalnya pakai `router.post()` Inertia terhadap
   endpoint `POST /mrp/run` yang JSON murni (bukan Inertia response) —
   menyebabkan error "All Inertia requests must receive a valid Inertia
   response". **Root cause murni frontend, MrpController TIDAK diubah.**
   Fix: ganti ke `fetch()` biasa + `router.reload({ only: [...] })` untuk
   refresh props Inertia setelahnya.
5. **Bug ditemukan & diperbaiki (kelas bug sama dengan `OeeGauge.vue`
   sesi 2026-07-20)**: setelah fix di atas, `MrpGrid.vue` tetap tidak
   menampilkan run baru — root cause: `const mrpRun = ref(props.
   initialMrpRun)` hanya membaca prop SEKALI saat mount, tidak ada
   `watch()` untuk sinkron ulang saat `router.reload()` mengirim prop
   baru. Fix: tambah `watch(() => props.initialMrpRun, (val) => {
   mrpRun.value = val })` TANPA guard `if (val)` (pelajaran sama persis
   dengan `OeeGauge.vue`). **Bug turunan**: `watch` sempat dipakai di
   kode tapi lupa diimpor dari `'vue'` (`import { ref, computed } from
   'vue'` tanpa `watch`) — menyebabkan silent failure. Sudah diperbaiki
   di `MrpGrid.vue` dan sekalian diterapkan preventif di `AlertBanner.vue`.
   **Pelajaran ditambahkan ke § Catatan Teknis Penting.**
6. **`RopGauge.vue`**: TIDAK live-update via Echo (beda dari `OeeGauge.
   vue`) — tidak ada event broadcast untuk perubahan inventory di
   `docs/architecture.md`. Awalnya tidak auto-fetch saat mount (perlu klik
   "Refresh" manual), diperbaiki dengan `onMounted(() => { if
   (materials.value.length === 0) refresh() })`.
7. Generate `Schedule` + `MrpRun` nyata via tinker untuk verifikasi (data
   fresh-seed tidak otomatis punya `Schedule`/`MrpRun`/`ReorderAlert` —
   itu hanya terpicu saat `POST /schedules/run` benar-benar dipanggil,
   bukan bagian dari seeder). **Terverifikasi end-to-end di browser**:
   AlertBanner, RopGauge (6 material, severity kritis/perlu-order/aman
   sesuai kalkulasi), MrpGrid (rowspan + highlight), tombol re-run MRP —
   semua bekerja tanpa error setelah fix di atas.

### Master Data CRUD (WorkCenter, Material, Product + BOM/Routing editor)

Sebelum sesi ini: **tidak ada Controller/Policy/Vue Page sama sekali**
untuk WorkCenter/Product/Material (diverifikasi via `find` sebelum mulai
— hanya `ProductionLogController`/`StoreProductionLogRequest` yang
ke-match keyword pencarian, murni false positive dari kata "Production").

1. **`WorkCenterController`** — CRUD penuh (`index/create/store/edit/
   update/destroy`) + `toggleActive()` (docs/architecture.md: "CRUD +
   toggle active"). `WorkCenterPolicy` SUDAH ADA sejak Engine 2 (dipakai
   untuk channel broadcast) — di-reuse, tidak dibuat ulang.
   `destroy()` guard: tolak jika masih dipakai di `Routing` manapun.
2. **`MaterialController`** — CRUD sederhana, tanpa nested editor.
   `MaterialPolicy` baru dibuat (pola identik `WorkCenterPolicy`: viewAny/
   view semua login, create/update/delete admin only — docs tidak
   mendefinisikan Policy khusus untuk Material, jadi diterapkan pola yang
   sama demi konsistensi). `destroy()` guard: tolak jika dipakai di BOM
   manapun ATAU punya `Inventory`/`InventoryParam` terkait.
3. **`ProductController`** — CRUD Product + **nested BOM editor +
   Routing editor** dalam SATU controller (bukan controller terpisah),
   sesuai `docs/architecture.md` yang eksplisit menyebut satu
   ProductController untuk keduanya. `ProductPolicy` baru dibuat, pola
   sama. Method tambahan: `storeBom/updateBom/destroyBom`,
   `storeRouting/updateRouting/destroyRouting` — semua nested di bawah
   `/products/{product}/bom/*` dan `/products/{product}/routings/*`.
   - `destroy()` Product guard: tolak jika masih punya Work Order (FK
     `ON DELETE RESTRICT`, `database.md`).
   - `storeBom()` guard: tolak duplikat `(product_id, material_id)`
     sebelum hit constraint DB, pesan error jelas.
   - `storeRouting()`/`updateRouting()` guard: tolak duplikat sequence
     per produk.
   - `destroyRouting()` guard: tolak jika baris routing sudah dipakai di
     `wo_operations` manapun (FK `ON DELETE RESTRICT`).
4. **`Pages/Products/Edit.vue`**: BOM editor + Routing editor pakai
   inline-edit per baris (klik "Edit" → baris jadi input, "Simpan"/
   "Batal") — bukan modal terpisah, konsisten filosofi tabular yang
   dipakai `MrpGrid.vue`. Dropdown "Tambah Material" di BOM editor
   difilter hanya menampilkan material yang BELUM ada di BOM produk
   tsb (murni UX preventif, validasi utama tetap di backend).
5. **Gap ditemukan & diperbaiki**: `HandleInertiaRequests::share()`
   sebelumnya hanya expose `auth.user` dengan kolom `id/name/email` —
   **TIDAK ada `role`**. Ini baru kelihatan dampaknya sekarang karena
   Master Data CRUD adalah fitur pertama yang butuh role-based UI hiding
   (tombol Tambah/Edit/Hapus hanya untuk admin). Fix: tambah `'role'` ke
   `->only(...)` di `share()`. Diverifikasi dengan user `role=operator`
   baru — tombol admin-only benar-benar hilang dari UI setelah fix
   (sebelumnya tombol tetap tampil meski backend sudah benar menolak
   403 — pure UX gap, bukan celah keamanan).
6. **Seluruh CRUD terverifikasi end-to-end di browser** oleh user:
   WorkCenter (index/create/edit/toggle-active/delete-guard), Material
   (index/create/edit/delete + delete-guard saat dipakai BOM/Inventory),
   Product (index/create + delete-guard saat punya WO) DAN **Products/
   Edit.vue lengkap dengan BOM editor + Routing editor (tambah/edit/hapus
   baris, badge count di Index ter-update) — dikonfirmasi user "semua
   berfungsi normal".**

**Full test suite akhir sesi 2026-07-23: 102 PASS, 303 assertions, TIDAK
ADA REGRESI** — seluruh pekerjaan sesi ini murni controller/policy/route/
Vue baru, tidak menyentuh Service manapun yang final (`MrpService`,
`EoqCalculatorService`, `OeeCalculatorService`, `DowntimeAnalysisService`,
`JobShopSchedulerService`, `GanttBuilderService`, `ScheduleApplierService`).

---

## Production Environment

| Item             | Value                                            |
| ---------------- | ------------------------------------------------ |
| OS               | Ubuntu 26.04 LTS "Resolute Raccoon" via WSL2 (dikoreksi 2026-07-22 — sebelumnya salah tercatat sebagai 24.04, lihat § Koreksi Penting) |
| URL (dev)        | http://127.0.0.1:8000 (via `php artisan serve`)  |
| Project path     | `~/workspace/factoryos/laravel`                  |
| Queue workers    | **PERMANEN sejak 2026-07-22** via Supervisor, config `/etc/supervisor/conf.d/factoryos-worker.conf`. Terpisah dari worker project lain (`geolevel`) yang dikelola Supervisor daemon yang sama. |
| Scheduler        | **AKTIF sejak 2026-07-22** via Supervisor, config `/etc/supervisor/conf.d/factoryos-scheduler.conf` (`schedule:work`, `numprocs=1` wajib). `CheckReorderAlertsJob` jalan `dailyAt('06:00')`, didaftarkan di `routes/console.php`. |
| WebSocket server | Soketi (terpasang di sisi client, belum dijalankan — `BROADCAST_CONNECTION=log`) |

### Commands

```bash
npm run build
npm run dev
php artisan serve
php artisan test
php artisan migrate
php artisan tinker
php artisan queue:failed               # cek job yang gagal
php artisan queue:flush                # hapus semua failed_jobs
php artisan schedule:list              # lihat semua scheduled task terdaftar
php artisan schedule:test              # jalankan simulasi satu scheduled task tanpa menunggu waktu asli
sudo supervisorctl status              # cek status semua proses Supervisor (factoryos-worker, factoryos-scheduler, geolevel-*)
sudo supervisorctl restart factoryos-worker:*
sudo supervisorctl restart factoryos-scheduler
```

> **Catatan penting**: `php artisan queue:work database` manual **TIDAK
> LAGI DIPERLUKAN** sejak 2026-07-22 — worker permanen sudah menjalankan
> ini otomatis via Supervisor.

**Catatan penting (masih relevan)**: kalau `ps aux | grep queue:work`
atau `grep schedule:work` menunjukkan proses jalan, VERIFIKASI dulu
working directory proses tersebut — bisa jadi milik project lain di
mesin yang sama.

**Login test users tersedia (dibuat sesi 2026-07-23, untuk verifikasi
role-based UI)**:
- `admin@factoryos.test` / `password` (role: admin)
- `operator@factoryos.test` / `password` (role: operator)

---

## Current Build Status

> **Update bagian ini setiap sesi sebelum mulai kerja.**

### ✅ Done

**Foundation**
- Laravel 12.63.0 + Breeze (Blade stack) + PostgreSQL 16 terkonfigurasi
- Migration lengkap 17 tabel custom (5 master data + 4 Engine 1 + 4 Engine 2 + 6 Engine 3, sesuai `docs/database.md`)
- 19 Eloquent Models dengan relationship lengkap (belongsTo/hasMany antar semua entity)
- Database seeder jalan: 2 shift, 5 work center, 10 material, 3 product (dengan BOM & routing acak), 15 work order dengan wo_operations ter-generate
- Kolom `users.role` (string biasa: admin, production_manager, ppic, operator) + method `isAdmin()`/`isProductionManager()`/`isPpic()`/`isOperator()` di `User` model
- `HandleInertiaRequests::share()` sekarang expose `auth.user.role` (BARU 2026-07-23, lihat § Koreksi Penting)

**Fondasi Frontend Inertia + Sanctum**
- `inertiajs/inertia-laravel:^2.0` → Inertia v3, `@inertiajs/vue3 ^3.6.1`, `vue ^3.5.40`
- `resources/views/app.blade.php`, `resources/js/inertia-app.js` (entry terpisah dari `app.js` Blade/Breeze)
- `app/Http/Middleware/HandleInertiaRequests.php` terdaftar via `$middleware->web(append: [...])`
- `laravel/sanctum v4.3.2` terpasang, `EnsureFrontendRequestsAreStateful` via `$middleware->api(prepend: [...])`
- **Tidak ada folder `resources/js/Layouts/`** — semua halaman standalone
- Disiplin ordering route statis-sebelum-wildcard diterapkan konsisten di `/schedules/*`, `/mrp/*`

**Engine 1 — Job Shop Scheduler (backend & frontend SELESAI & teruji)**
- `SchedulingAlgorithmInterface` + 4 algoritma (Spt/Edd/Cr/Fifo), semua bcmath
- `JobShopSchedulerService` (run, compareAll — **return array asosiatif, WAJIB `array_values()`**, computeMetrics)
- `WoOperationGeneratorService` (`App\Services\WorkOrder\`, bukan `App\Services\Scheduling\`)
- `WorkOrderController`, `WorkOrderStatusService`, `WorkOrderPolicy`
- `GanttBuilderService`, `ScheduleController` (run/compareAll/ganttData/apply, dispatch `ScheduleCreated`)
- `ScheduleApplierService`
- `GanttChart.vue`, `KpiCard.vue`, `Schedules/Compare.vue`, `Schedules/Show.vue`
- Route `schedules.compare` (GET, render halaman) ditambahkan 2026-07-22
- 23 test PASS

**Engine 2 — OEE & Downtime Analytics (backend & frontend SELESAI, pipeline queue nyata teruji)**
- `OeeCalculatorService` (compute/trendData/benchmarkVsWorldClass, bcmath scale 6, `round()`/`roundSigned()` manual)
- `DowntimeAnalysisService::paretoDowntime()`
- `ProductionLogController`, `DowntimeController`, `ProductionLogPolicy`
- `ProductionLogObserver` → `ProductionLogSaved` → `RecalculateOeeJob` → broadcast `OeeUpdated` (channel `work-center.{id}`, event `.oee.updated`)
- `WorkCenterPolicy` (viewAny/view semua login, create/update/delete admin only — **di-reuse untuk `WorkCenterController` CRUD sesi 2026-07-23**)
- `OeeController` (dashboard/pareto/trend/benchmark/latest-snapshot)
- `ProductionLogs/{Index,Create,Show,Edit}.vue`, `OeeGauge.vue` (bug stale-state diperbaiki), `ParetoChart.vue`, `OEE/Dashboard.vue`
- Soketi masih **belum dijalankan nyata** (`BROADCAST_CONNECTION=log`), komponen Echo siap pakai
- 56 test PASS

**Engine 3 — Inventory Optimizer (backend SELESAI PENUH, Frontend SELESAI sesi 2026-07-23)**

*Backend (final, tidak diubah sejak 2026-07-20/22):*
- `EoqCalculatorService`, `MrpService` — **FINAL, JANGAN DIUBAH**
- `ScheduleCreated` event + `TriggerMrpRunListener` → `RunMrpJob`
- `CheckReorderAlertsJob` (scheduled 06:00 via Supervisor)
- Model `Inventory` — `$table = 'inventory'` eksplisit
- `MrpController` — 4 endpoint: `run()`, `show()`, `alerts()` (JSON, sejak 2026-07-22), **`dashboard()` (Inertia render, BARU 2026-07-23)**
- Seeder Engine 3 lengkap: 6 material dari BOM dapat `Inventory`+`InventoryParam` bervariasi
- 11 test PASS (5 Eoq + 6 Mrp)

*Frontend (BARU, SELESAI & teruji end-to-end 2026-07-23):*
- **`InventoryController::status()`** (BARU) — `GET /inventory/status`, read-only join Material+Inventory+InventoryParam, untuk `RopGauge.vue`
- **`AlertBanner.vue`** — daftar reorder alert per status (open/acknowledged/ordered), tombol update status ADA di UI tapi endpoint backend-nya BELUM dibuat (utang teknis disengaja, lihat § Utang Teknis)
- **`RopGauge.vue`** — visual qty_on_hand vs safety_stock vs ROP per material, severity kritis/perlu-order/aman, auto-fetch on mount, TIDAK live-update (tidak ada broadcast event inventory)
- **`MrpGrid.vue`** — tabel native (bukan D3) pivot material × periode (GR/SR/POH/NR/POR), rowspan per material, highlight NR/POR
- **`Pages/Mrp/Dashboard.vue`** — gabungan ketiganya + tombol "Jalankan MRP Ulang" (pakai `fetch()`, BUKAN `router.post()` — endpoint JSON murni)
- Route: `GET /mrp` (`mrp.dashboard`)
- **Terverifikasi end-to-end di browser**: 6 material tampil di RopGauge dengan severity benar, MrpGrid pivot benar dengan highlight, re-run MRP berhasil update grid tanpa reload manual

**Master Data CRUD (BARU, SELESAI PENUH sesi 2026-07-23, dari nol)**
- **`WorkCenterController`** — CRUD + `toggleActive()`, pakai `WorkCenterPolicy` existing. `destroy()` guard vs `Routing`.
- **`MaterialController`** — CRUD sederhana. `MaterialPolicy` baru (pola sama WorkCenterPolicy). `destroy()` guard vs BOM/Inventory.
- **`ProductController`** — CRUD + nested BOM editor + Routing editor (satu controller, sesuai docs). `ProductPolicy` baru. `destroy()` guard vs WorkOrder. `storeBom/updateBom/destroyBom`, `storeRouting/updateRouting/destroyRouting` dengan guard duplikat & FK RESTRICT.
- **Halaman Vue**: `WorkCenters/{Index,Create,Edit}.vue`, `Materials/{Index,Create,Edit}.vue`, `Products/{Index,Create,Edit}.vue` (Edit.vue berisi BOM editor + Routing editor inline-edit per baris)
- Route: `work-centers.*` (+ `toggle-active`), `materials.*`, `products.*` (+ `bom.*`, `routings.*` nested)
- **`HandleInertiaRequests::share()`** — tambah `role` ke `auth.user` (gap pre-existing baru kelihatan sekarang)
- **Terverifikasi end-to-end di browser oleh user**: seluruh CRUD 3 entity, toggle active, delete guards (BOM/Inventory/WorkOrder/Routing), role-based UI hiding (admin vs operator), dan BOM/Routing editor lengkap (tambah/edit/hapus baris, badge count update di Index) — **"semua berfungsi normal"**.

**Total: 102 test PASS (303 assertions), full suite, TIDAK ADA REGRESI dari sesi 2026-07-23** (murni penambahan controller/policy/route/Vue baru, tidak ada perubahan Service final maupun test baru ditulis sesi ini).

### 🔄 In Progress
- (belum ada — siap mulai task baru)

### ⏳ Not Started
- `ScheduleController::show()` masih closure inline di `routes/web.php`
- `ExportService` (PDF/Excel per engine) — `barryvdh/laravel-dompdf` dan `maatwebsite/excel` belum di-`composer require`
- Dashboard KPI lintas 3 engine
- Soketi belum benar-benar dijalankan end-to-end (`BROADCAST_CONNECTION` masih `log`)
- Feature test untuk `OeeController` dan `EoqCalculatorService::computeAndSave()`
- Endpoint PATCH status untuk `ReorderAlert` (dipakai tombol di `AlertBanner.vue`, UI sudah siap tapi backend belum ada — keputusan otorisasi siapa yang boleh acknowledge/order belum didiskusikan)

---

## ⚠️ Utang Teknis / Perlu Investigasi

**Status per sesi 2026-07-23: Frontend MRP dan Master Data CRUD SEMUA SELESAI. Daftar di bawah adalah utang teknis yang MASIH TERBUKA.**

1. `ScheduleController::show()` masih closure inline — konsistensi gaya kode, bukan bug.
2. **`ExportService` belum dimulai** — dompdf & Laravel Excel belum di-`composer require`.
3. **Soketi belum benar-benar dijalankan** — `BROADCAST_CONNECTION` masih `log`.
4. **Dashboard KPI lintas 3 engine belum dimulai.**
5. **Feature test untuk `OeeController` dan `EoqCalculatorService::computeAndSave()` belum ada.**
6. **Ketidaksesuaian versi dokumen dengan realita OS** — `docs/prd.md` masih menyebut "Ubuntu 24.04 LTS", realita `26.04 LTS`. Perlu dikoreksi manual oleh pemilik project di source docs (read-only dari sisi sesi Claude).
7. `e2e-production-logs.mjs` di root project — skrip diagnostik ad-hoc, aman dihapus.
8. **BARU (2026-07-23): Endpoint update status `ReorderAlert` belum ada** — `AlertBanner.vue` punya tombol "Tandai Dilihat"/"PO Dibuat" yang akan 404 sampai endpoint `PATCH /mrp/alerts/{id}/status` dibuat. Sengaja tidak dibuat sesi ini karena mengubah status alert adalah keputusan bisnis (role apa yang boleh?) yang perlu didiskusikan terpisah dari scope "frontend MRP" murni.
9. **BARU (2026-07-23, minor)**: `Pages/Products/Create.vue` mengarahkan user ke `products.edit` setelah submit, tapi belum ada state "draft belum lengkap" yang jelas secara visual di luar badge count di Index — cukup untuk v1, bisa dipercantik di sesi polish nanti.

**Item yang SUDAH TERTUTUP sesi 2026-07-22 (referensi historis, dipertahankan):**
- ~~Queue worker FactoryOS tidak permanen~~ → **SELESAI**
- ~~`Schedules/Show.vue` bug 500 error~~ → **SELESAI**
- ~~`MrpController` belum ada~~ → **SELESAI**
- ~~`CheckReorderAlertsJob` belum dijadwalkan otomatis~~ → **SELESAI**
- ~~Data seeder Engine 3 minim~~ → **SELESAI**

**Item yang SUDAH TERTUTUP sesi 2026-07-23 (referensi historis, dipertahankan):**
- ~~Frontend MRP (RopGauge/MrpGrid/AlertBanner) belum dimulai~~ → **SELESAI**
- ~~Master data CRUD belum ada UI/Controller~~ → **SELESAI** (WorkCenter, Material, Product + BOM/Routing editor)
- ~~`HandleInertiaRequests` tidak expose `role`~~ → **SELESAI**

---

## Koreksi Dokumen (formula)

`docs/oee-formulas.md` dan `docs/engineering-rules.md` sebelumnya menyatakan
hasil OEE contoh manual = 0.771099. **Ini salah hitung di dokumen aslinya.**
Hasil yang benar secara matematis: 0.875000 × 0.904762 × 0.973684 = **0.770833**.
Sudah dikoreksi di kedua file docs tersebut dan di semua test terkait.

**Koreksi tambahan (2026-07-22)**: `docs/prd.md` menyatakan OS environment
"Ubuntu 24.04 LTS" — realita terverifikasi via `cat /etc/os-release` adalah
`VERSION_ID="26.04"` ("Resolute Raccoon"). Perlu dikoreksi di source docs
oleh pemilik project (file docs bersifat read-only dari sisi sesi Claude ini).

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
- Engine 1 → `ScheduleCreated` event → Engine 3 (`TriggerMrpRunListener` → `RunMrpJob`) hitung kebutuhan material — terhubung nyata, diproses otomatis via queue worker permanen
- Engine 3 → `CheckReorderAlertsJob` berjalan otomatis tiap hari 06:00 → `ReorderAlertRaised` (listener `NotifyPpicListener` belum diimplementasikan, di luar scope)
- Engine 3 → **sekarang punya UI penuh** (Dashboard MRP: AlertBanner + RopGauge + MrpGrid, sesi 2026-07-23) — sebelumnya hanya backend
- Master Data (WorkCenter/Product/Material + BOM/Routing) → **sekarang punya UI penuh** (sesi 2026-07-23) — fondasi data untuk Engine 1 (routing) dan Engine 3 (BOM)

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

**Catatan**: beberapa hal di `docs/architecture.md` ternyata tidak sesuai
implementasi nyata — controller yang sudah ada pakai validasi inline
`$request->validate()`, bukan Form Request terpisah (kecuali Engine 2
yang punya beberapa Form Request). Kalau ragu, cek kode controller yang
sudah ada dulu sebelum asumsi dari docs. **Selalu verifikasi keberadaan
file dengan `find`/`cat`/`ls` sebelum berasumsi sesuatu sudah ada,
sekalipun didokumentasikan.**

---

## Main Services

| Service                   | Tanggung Jawab                                     | Status       |
| ------------------------- | --------------------------------------------------- | ------------ |
| `JobShopSchedulerService` | Jalankan 4 dispatching rules, simpan schedule       | ✅ Selesai   |
| `GanttBuilderService`     | Transform assignments → D3.js-ready dataset         | ✅ Selesai   |
| `ScheduleApplierService`  | Terapkan schedule terpilih ke wo_operations         | ✅ Selesai   |
| `WoOperationGeneratorService` | Generate wo_operations dari routing (`App\Services\WorkOrder\`) | ✅ Selesai |
| `OeeCalculatorService`    | Hitung OEE, trend data, benchmark vs world class    | ✅ Selesai   |
| `DowntimeAnalysisService` | Pareto analysis downtime (agregat cross-log)        | ✅ Selesai   |
| `EoqCalculatorService`    | EOQ, Safety Stock, ROP, Total Annual Cost (bcmath)  | ✅ Selesai (final, jangan diubah) |
| `MrpService`              | MRP explosion: schedule → material requirements, reorder alerts | ✅ Selesai (final, jangan diubah) |
| `ExportService`           | Orkestrasi PDF & Excel export per engine            | ⏳ Belum     |

---

## Main Controllers (update 2026-07-23)

| Controller | Tanggung Jawab | Status |
|---|---|---|
| `WorkOrderController` | CRUD WO + generate operations + status transition | ✅ Selesai |
| `ScheduleController` | run/compareAll/ganttData/apply | ✅ Selesai |
| `ProductionLogController`, `DowntimeController` | CRUD log produksi + downtime | ✅ Selesai |
| `OeeController` | dashboard/pareto/trend/benchmark | ✅ Selesai |
| `MrpController` | run/show/alerts (JSON) + **dashboard() (Inertia, BARU)** | ✅ Selesai |
| `InventoryController` | **status() (BARU)** — read-only inventory vs ROP | ✅ Selesai |
| `WorkCenterController` | **CRUD + toggleActive() (BARU)** | ✅ Selesai |
| `MaterialController` | **CRUD (BARU)** | ✅ Selesai |
| `ProductController` | **CRUD + nested BOM/Routing editor (BARU)** | ✅ Selesai |
| `ExportController` | PDF/Excel per engine | ⏳ Belum |
| `DashboardController` | KPI ringkasan lintas 3 engine | ⏳ Belum |

---

## Formulas Quick Reference

**ENGINE 1 — JOB SHOP SCHEDULING**
SPT score = processing_time (ascending)
EDD score = due_date (ascending)
CR score = (due_date - now).minutes / remaining_processing_time (ascending)
FIFO score = work_order.created_at (ascending)
Makespan = max(completion_time) semua operations
Tardiness_i = max(0, last_op_end_i - due_date_i)
Total Tard. = Σ Tardiness_i
Mean Flow = Σ(last_op_end_i - release_date_i) / n

**ENGINE 2 — OEE (ISO 22400)**
Availability = (Planned - Downtime) / Planned
Performance = (Output × IdealCycleTime) / OperatingTime [cap 1.0]
Quality = GoodOutput / TotalOutput
OEE = Availability × Performance × Quality

Contoh manual tervalidasi: Availability=0.875000, Performance=0.904762,
Quality=0.973684, OEE=0.770833 (bukan 0.771099).

**ENGINE 3 — INVENTORY**
EOQ = √(2 × D × S / H)
Safety Stock = Z × σ_d × √(LT)
ROP = (avg_daily_demand × LT) + Safety Stock
Net Req(t) = max(0, GrossReq(t) - ProjOnHand(t-1) - ScheduledReceipts(t))
Planned Order Release = roundUpToEoq(Net Req(t)), disimpan di period_date
yang sama (need-date)

Contoh manual tervalidasi (`EoqCalculatorServiceTest`):
D=1200, S=150000, H=5000 → EOQ=268.328157;
Z=1.6450, σ_d=3, LT=7 → Safety Stock=13.056783, ROP=83.056783.

Contoh manual tervalidasi (`MrpServiceTest`, docs/inventory.md § Contoh
MRP Grid — Besi Plat 2mm, LT=3 hari, EOQ=100):
on-hand=50 + SR=100 di t1 → tidak ada Net Requirement;
on-hand=10, GR=30 di t2 → NR=20 → roundUpToEoq(20,100)=100.

Reorder Alert tervalidasi (`MrpServiceTest`):
qty_on_hand=20 + qty_on_order=0 ≤ rop=38.5477 → 1 alert 'open' dibuat,
`current_qty`='20.0000' (cast decimal:4 model ReorderAlert, BUKAN scale 6
bcmath internal MrpService). Idempotency guard terverifikasi.

---

## Catatan Teknis Penting (pelajaran dari sesi-sesi sebelumnya)

- **bcmath tidak pernah membulatkan**, selalu truncate. Pakai helper
  `round()`/`roundSigned()` manual — pola baku identik di
  `OeeCalculatorService`/`DowntimeAnalysisService`/`EoqCalculatorService`/
  `MrpService`. Salin pola ini persis untuk service kalkulasi baru.
- **Cast model vs scale bcmath internal BISA BERBEDA** — selalu cek
  `protected $casts` model sebenarnya sebelum menulis assertion test.
- **Laravel 12 tidak pakai `EventServiceProvider` bawaan** — event/listener
  diregister manual di `AppServiceProvider::boot()`.
- **Laravel 12 tidak punya `app/Console/Kernel.php`** — scheduled task
  didaftarkan di `routes/console.php`.
- **Sebuah Event/Listener/Service/Controller yang disebut di dokumen
  belum tentu benar-benar ada** — selalu verifikasi dengan
  `find app/... -iname "*Nama*"` sebelum berasumsi. Berulang kali terjadi:
  `ScheduleCreated`, `WoOperationGeneratorService` (salah namespace),
  dan Master Data Controllers (2026-07-23: benar-benar nol, bukan cuma
  salah lokasi).
- **`bootstrap/app.php`**: `withRouting(channels: ...)` sudah cukup untuk
  `routes/channels.php`. Jangan tambahkan `withBroadcasting()` juga.
- **`bootstrap/app.php` middleware**: `HandleInertiaRequests` via
  `$middleware->web(append: [...])`; Sanctum via `$middleware->api(prepend: [...])`.
- **Route statis vs wildcard**: path statis WAJIB didaftarkan SEBELUM
  wildcard dengan pola sama. Diterapkan konsisten di `/schedules/*`,
  `/mrp/*`, dan **`/products/*` (sesi 2026-07-23: `products.bom.*`/
  `products.routings.*` nested tidak konflik dengan `/products/{product}`
  karena jumlah segmen path berbeda — Laravel bisa bedakan otomatis,
  tapi tetap didaftarkan berurutan untuk keterbacaan).**
- **`JobShopSchedulerService::compareAll()` mengembalikan array
  ASOSIATIF** — WAJIB `array_values()` sebelum dikirim sebagai prop Inertia.
- **Policy di Laravel 12** auto-discovered dari nama file `{Model}Policy`
  di `app/Policies/` — tidak perlu register manual. Diverifikasi ulang
  sesi 2026-07-23 untuk `MaterialPolicy` dan `ProductPolicy` baru — bekerja
  tanpa registrasi tambahan.
- **Test dengan Observer aktif** di `QUEUE_CONNECTION=sync`: isolasi
  dengan `Event::fake([...])` di `setUp()` kalau perlu.
- **Query rentang tanggal WAJIB pakai `whereDate()`**, bukan `whereBetween()`.
- **Event Echo dengan `broadcastAs()` custom**: listener client WAJIB
  pakai titik di depan (`.oee.updated`, bukan `oee.updated`).
- **Env var fallback JS**: `??` TIDAK menangkap string kosong `""`.
- **Watcher Vue pada prop yang bisa berubah setelah mount**: JANGAN pakai
  guard `if (val) target.value = val` — selalu sinkronkan tanpa syarat.
  **Kasus baru 2026-07-23**: `MrpGrid.vue` awalnya `ref(props.
  initialMrpRun)` TANPA `watch()` sama sekali (bukan cuma guard yang
  salah — `watch` benar-benar tidak ada), sehingga `router.reload()` dari
  parent tidak pernah ter-refleksi ke child. Prop reactivity untuk objek
  yang bisa diganti utuh oleh parent (bukan cuma primitif) SELALU butuh
  `watch()` eksplisit di child jika child menyimpan salinan lokal via `ref()`.
- **BARU (2026-07-23): Import Vue Composition API yang dipakai tapi lupa
  di-import (`watch`, `onMounted`, dll.) menyebabkan silent failure di
  production build** — tidak selalu muncul sebagai error jelas di awal,
  kadang baru kelihatan sebagai "fitur tidak reaktif" tanpa pesan error
  eksplisit sampai dicek Console tab. Selalu cek `import { ... } from
  'vue'` mencakup SEMUA composable yang dipakai di `<script setup>`
  sebelum mengirim kode.
- **BARU (2026-07-23): Endpoint controller yang mengembalikan
  `response()->json(...)` murni (bukan `Inertia::render(...)`) TIDAK
  BOLEH dipanggil dari frontend pakai `router.post()`/`router.get()`
  Inertia** — Inertia mewajibkan response berupa Inertia response
  (render atau redirect), akan error "All Inertia requests must receive
  a valid Inertia response" kalau dikirim JSON polos. Gunakan `fetch()`
  biasa untuk endpoint JSON, dan `router.post()`/`Link`/`useForm()` HANYA
  untuk endpoint yang benar-benar `Inertia::render()` atau `redirect()`/
  `back()`. Pola ini sudah konsisten dipakai di seluruh project
  (`GanttChart.vue`, `ParetoChart.vue` pakai `fetch()` ke endpoint JSON;
  `useForm()` dipakai untuk form yang di-`redirect()`/`back()`) — bug di
  `Mrp/Dashboard.vue` adalah pelanggaran pertama pola ini yang baru
  ketahuan sesi ini.
- **BARU (2026-07-23): Kolom shared props Inertia yang di-`->only(...)`
  di `HandleInertiaRequests::share()` adalah satu-satunya sumber data
  yang tersedia di SEMUA halaman Vue tanpa eksplisit di-pass per
  controller** — kalau ada kebutuhan baru (mis. `role` untuk UI hiding)
  yang belum ada di daftar kolom tsb, itu bukan bug di controller manapun,
  tapi gap di middleware ini. Backend authorize() tetap jadi source of
  truth keamanan; gap di shared props hanya berdampak UX (tombol
  tetap tampil meski backend akan menolak), bukan celah keamanan nyata.
- **Model Eloquent WAJIB override `$table` eksplisit** kalau nama tabel
  migration tidak mengikuti konvensi plural default Laravel.
- **`$fillable` yang sengaja tidak menyertakan `created_at`** (tabel
  immutable): WAJIB `forceFill([...])->save()`, bukan `create([...])`.
- **Proses lain di `ps aux` dengan nama command sama bisa jadi milik
  project LAIN** — selalu verifikasi working directory.
- **Seeder factory tidak otomatis menjalankan efek samping controller**
  (mis. generate child records) — cek dan tiru manual di seeder jika perlu.
- **Verifikasi "selesai" harus end-to-end**: unit/feature test PASS tidak
  menjamin frontend bisa diakses/reaktif dengan benar di browser. Selalu
  build + buka browser + cek Network/Console tab. **Sesi 2026-07-23
  konsisten menerapkan ini**: setiap komponen baru (Frontend MRP, Master
  Data CRUD) diverifikasi via checkpoint di browser sebelum dianggap
  selesai, dan `claude.md` baru ditulis SETELAH user eksplisit
  mengonfirmasi verifikasi terakhir (BOM/Routing editor) — bukan
  diasumsikan selesai hanya karena route/controller terdaftar.

---

## Roadmap per Phase

### Phase 1 — Foundation (Week 1–2) ✅ SELESAI PENUH
- [x] Laravel scaffolding + Breeze
- [x] Inertia + Vue 3
- [x] Semua migrations sekaligus
- [x] Models + relationships + factories
- [x] **Master data CRUD: WorkCenter, Product, Material — SELESAI 2026-07-23**
- [x] **BOM editor + Routing sequence editor — SELESAI 2026-07-23**
- [x] WorkOrder CRUD + generate wo_operations dari routing

### Phase 2 — Engine 1: Scheduler (Week 3–4) ✅ SELESAI PENUH
- [x] Semua item selesai (lihat sesi-sesi sebelumnya)
- [ ] `ScheduleController::show()` masih closure inline (kosmetik, bukan bug)

### Phase 3 — Engine 2: OEE (Week 5–6) ✅ SELESAI (kecuali Soketi live)
- [x] Semua item backend & frontend selesai
- [ ] Soketi benar-benar dijalankan & dites end-to-end (masih driver `log`)

### Phase 4 — Engine 3: Inventory (Week 7–8) ✅ SELESAI PENUH (backend 2026-07-22, frontend 2026-07-23)
- [x] EoqCalculatorService, MrpService — FINAL
- [x] ScheduleCreated event, RunMrpJob, CheckReorderAlertsJob
- [x] MrpController (run/show/alerts + **dashboard() BARU**)
- [x] **RopGauge.vue, MrpGrid.vue, AlertBanner.vue — SELESAI 2026-07-23**
- [x] **InventoryController::status() — SELESAI 2026-07-23**
- [ ] Feature test EoqCalculatorService::computeAndSave() (butuh RefreshDatabase)
- [ ] Endpoint PATCH status ReorderAlert (utang teknis disengaja)

### Phase 5 — Integration & Polish (Week 9–10) — sebagian dimulai
- [ ] Dashboard KPI lintas 3 engine
- [ ] Export PDF & Excel per engine (dompdf, Laravel Excel belum di-install)
- [x] **Master data CRUD (WorkCenter, Product, Material) + BOM/Routing editor — SELESAI 2026-07-23**
- [ ] Full test suite + canonical seeder review
- [x] Data seeder Engine 3 lebih lengkap
- [x] Queue worker permanen (Supervisor)
- [x] Fix bug `Schedules/Show.vue` `compareUrl`
- [x] **Role-based UI hiding di Master Data (HandleInertiaRequests::share role) — SELESAI 2026-07-23**

---

## Urutan Kerja Per Sesi

1. Update `Current Build Status` di file ini
2. Baca docs yang relevan — **cross-check dengan kode nyata kalau ragu**,
   **verifikasi keberadaan file/class/namespace dengan `find`/`cat` sebelum
   asumsi, sekalipun didokumentasikan.**
3. Queue worker & scheduler PERMANEN via Supervisor — verifikasi
   `sudo supervisorctl status` di awal sesi (WSL2 restart bisa
   memengaruhi status meski daemon `enabled` di systemd).
4. migration → model → factory → service → controller → Vue page
5. Unit test setiap Service baru sebelum lanjut
6. **Verifikasi end-to-end di browser DAN di database nyata** — jangan
   cuma andalkan `php artisan test`. **Untuk fitur berbasis endpoint
   JSON vs Inertia, pastikan frontend memakai tool yang tepat (`fetch()`
   vs `router.post()`/`useForm()`) — lihat § Catatan Teknis Penting.**
7. `php artisan test` (full suite) sebelum selesai sesi — pastikan tidak
   ada regresi
8. Catat temuan/bug/utang teknis baru di § Utang Teknis
9. **Jangan tulis `claude.md` final sebelum user eksplisit mengonfirmasi
   verifikasi browser untuk checkpoint TERAKHIR sesi** — kejadian nyata
   2026-07-23: sempat diminta update `claude.md` sebelum konfirmasi
   `Products/Edit.vue` (BOM/Routing editor) diterima, sengaja ditunda
   dengan bertanya balik dulu.

---

## Prompt Sesi Berikutnya (Draf ROKC — Fitur Baru)

Frontend MRP dan Master Data CRUD dari draf sesi lalu SUDAH TUNTAS.
Kandidat fokus sesi berikutnya (prioritas disarankan urut dari atas,
sesuai urutan asli yang belum dikerjakan):

1. **Dashboard KPI lintas 3 engine** (FR-10) — `DashboardController` baru,
   agregasi dari `JobShopSchedulerService`/`OeeCalculatorService`/
   `MrpService` yang sudah ada (JANGAN tulis ulang logic kalkulasi).
   KPI: Engine 1 (WO aktif, WO terlambat, makespan jadwal aktif),
   Engine 2 (OEE rata-rata hari ini, mesin OEE terendah), Engine 3
   (reorder alert open, material stok kritis — bisa reuse
   `InventoryController::status()` yang baru dibuat sesi 2026-07-23).
2. **ExportService** — `barryvdh/laravel-dompdf` & `maatwebsite/excel`
   belum di-`composer require`. Scope penuh sesuai `docs/exports.md`:
   PDF jadwal produksi, PDF OEE harian, Excel MRP grid (3 sheet), Excel
   OEE trend bulanan (3 sheet), semua via background job.
3. **Soketi aktivasi nyata** — ganti `BROADCAST_CONNECTION` ke `pusher`,
   isi `VITE_PUSHER_*`, jalankan `npx soketi start`. Komponen Vue sudah
   siap tanpa ubah kode. Paling cepat, cocok jadi quick win di awal sesi.

**Item kecil tambahan yang bisa diselipkan kapan saja (tidak perlu jadi
fokus utama sesi)**:
- Endpoint `PATCH /mrp/alerts/{id}/status` untuk tombol `AlertBanner.vue`
  yang sudah siap di UI — perlu diskusi otorisasi dulu (role apa yang
  boleh acknowledge/order).
- Pindahkan `ScheduleController::show()` closure inline jadi method
  controller sesungguhnya (kosmetik).
- Feature test `EoqCalculatorService::computeAndSave()`.

Sebelum mulai sesi berikutnya, baca `claude.md` ini secara lengkap
(khususnya § Koreksi Penting 2026-07-23 dan § Catatan Teknis Penting —
terutama soal `fetch()` vs `router.post()` untuk endpoint JSON, dan
kewajiban `watch()` eksplisit untuk prop objek yang bisa berubah), serta
verifikasi status Supervisor (`sudo supervisorctl status`) masih
`RUNNING` untuk `factoryos-worker` dan `factoryos-scheduler`.
```

Ringkasan sesi ini (8 baris):
1. **Frontend MRP** selesai penuh: `AlertBanner.vue`, `RopGauge.vue`, `MrpGrid.vue`, `Pages/Mrp/Dashboard.vue`, `InventoryController::status()`, `MrpController::dashboard()`.
2. **Master Data CRUD** selesai penuh dari nol: `WorkCenterController`+toggle, `MaterialController`, `ProductController`+nested BOM/Routing editor, 3 Policy baru, 9+ halaman Vue.
3. Fix: `HandleInertiaRequests::share()` tambah `role` untuk UI hiding admin-only.
4. Bug ditemukan & diperbaiki: salah pakai `router.post()` Inertia untuk endpoint JSON murni; `watch` dipakai tanpa diimpor; `MrpGrid.vue` tidak reaktif karena tidak ada `watch()` pada prop objek.
5. `MrpService`/`EoqCalculatorService`/service final lainnya **tidak disentuh sama sekali**.
6. **102 test PASS, 303 assertions, tidak ada regresi.**
7. Semua checkpoint (WorkCenter, Material, Product+BOM+Routing, MRP Dashboard) diverifikasi end-to-end di browser oleh user sebelum ditandai selesai.
8. Utang teknis baru dicatat: endpoint PATCH status ReorderAlert belum ada (disengaja, perlu diskusi otorisasi).