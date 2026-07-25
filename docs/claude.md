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
| Backend      | Laravel 12.63.0 (PHP 8.5.4)         |
| Database     | PostgreSQL 16                       |
| Frontend     | Inertia.js v3 + Vue 3 + Vite        |
| UI Library   | Tailwind CSS v3 (custom scoped CSS per komponen, bukan utility-first murni) |
| Charts       | D3.js (Gantt, Pareto, trend chart — semua custom SVG, bukan Chart.js) |
| Gantt        | Custom SVG via D3.js                |
| PDF Export   | **barryvdh/laravel-dompdf v3.1.2 — TERINSTALL & AKTIF sejak sesi 2026-07-24** |
| Excel Export | **phpoffice/phpspreadsheet v5.9.0 langsung — TERINSTALL & AKTIF sejak sesi 2026-07-24 (BUKAN maatwebsite/excel, lihat § Koreksi Penting)** |
| Auth         | Laravel Breeze (Blade stack)        |
| Real-time    | Laravel Echo + Soketi (self-hosted, terpasang tapi belum aktif — `BROADCAST_CONNECTION=log`) |
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
> bersifat standalone, tanpa layout wrapper bersama (nav/sidebar).
>
> **`/dashboard` BUKAN LAGI Blade Breeze kosong** — sejak sesi 2026-07-24,
> route ini di-render oleh `DashboardController::index()` (Inertia KPI
> Dashboard lintas 3 engine, FR-10). Lihat § Koreksi Penting (2026-07-24).

---

## ⚠️ Koreksi Penting (2026-07-19)

Sesi-sesi sebelumnya menandai beberapa hal sebagai "SELESAI & teruji" yang
ternyata **tidak pernah benar-benar bisa dijalankan/diverifikasi di browser**.
Pelajaran untuk sesi berikutnya: **"kode sudah ditulis" ≠ "sudah bekerja"**
— selalu verifikasi end-to-end (build + buka browser + cek Network/Console),
jangan cuma percaya status di dokumen ini tanpa cek ulang kalau ada keraguan.

Yang ditemukan salah/hilang:
1. **Versi Laravel salah tercatat sebagai "11"** — versi sebenarnya
   **12.63.0** sejak awal project. Semua referensi sudah dikoreksi.
2. **Inertia tidak pernah ter-bootstrap** meski banyak file `.vue` sudah
   ditulis dan ditandai "SELESAI & teruji".
3. **Laravel Sanctum tidak ter-install**, meski endpoint sudah pakai
   middleware `auth:sanctum` dan ditandai "SELESAI".
4. **Beberapa route halaman tidak pernah didaftarkan** meski controller &
   Vue page-nya sudah lengkap.

Semua ini sudah diperbaiki sesi 2026-07-19. **Rekomendasi**: sebelum
menandai sesuatu "SELESAI", verifikasi dengan benar-benar membuka browser
dan mengecek Network/Console tab.

---

## ⚠️ Koreksi Penting (2026-07-20 / 2026-07-22)

1. **`oee_snapshots` kosong bukan karena bug kode** — root cause murni
   operasional: tidak pernah ada queue worker yang jalan untuk FactoryOS.
2. **Bug ditemukan & diperbaiki**: `OeeGauge.vue` — watcher guard `if (val)`
   mencegah reset ke `null`. Fix: selalu sinkronkan tanpa syarat.
3. **`app/Events/ScheduleCreated.php` didokumentasikan tapi TIDAK PERNAH
   benar-benar dibuat** — dibangun dari nol sesi ini.
4. **Bug ditemukan & diperbaiki**: model `Inventory` tidak override
   `protected $table`, Eloquent menebak `inventories` (salah, harusnya
   `inventory`). Fix: `protected $table = 'inventory';` eksplisit.
5. **Bug `Schedules/Show.vue` `compareUrl` 500 error** — diperbaiki
   sesi 2026-07-22 (lihat di bawah).

---

## ⚠️ Koreksi Penting (2026-07-22, sesi penutupan 6 utang teknis)

1. **Fix bug `Schedules/Show.vue` "↺ Bandingkan Ulang" 500 error** — tidak
   ada route GET untuk `Schedules/Compare.vue`. Fix: route baru
   `GET /schedules/compare`. **`JobShopSchedulerService::compareAll()`
   mengembalikan array ASOSIATIF** — WAJIB `array_values()` sebelum
   dikirim sebagai prop Inertia.
2. **`MrpController` dibuat** — 3 endpoint JSON: `run()`, `show()`, `alerts()`.
3. **Test `MrpService::checkReorderAlerts()` ditambahkan** — 3 test baru.
4. **Queue worker permanen via Supervisor** — `factoryos-worker` terpisah
   dari `geolevel-*` yang sudah lama jalan di daemon Supervisor yang sama.
5. **Seeder Engine 3 dilengkapi** — `Inventory`+`InventoryParam` untuk
   setiap material yang dipakai di BOM, EOQ/SafetyStock/ROP dihitung nyata.
6. **Laravel Scheduler untuk `CheckReorderAlertsJob` diaktifkan** — via
   Supervisor + `schedule:work` (`numprocs=1` WAJIB), didaftarkan di
   `routes/console.php` (Laravel 12 tidak punya `app/Console/Kernel.php`).
7. **Bug seeder**: `WorkOrder::factory()` tidak generate `wo_operations` —
   fix: seeder panggil `WoOperationGeneratorService::generate()` manual.
8. **Ketidaksesuaian versi OS**: realita `Ubuntu 26.04 LTS`, bukan 24.04.

**Full test suite akhir sesi 2026-07-22: 102 PASS, 303 assertions.**

---

## ⚠️ Koreksi Penting (2026-07-23, sesi Frontend MRP + Master Data CRUD)

### Frontend MRP
1. **`InventoryController::status()`** baru — `GET /inventory/status`.
2. **`MrpController::dashboard()`** — `GET /mrp`, render `Mrp/Dashboard.vue`.
3. **`AlertBanner.vue`, `RopGauge.vue`, `MrpGrid.vue`** baru.
4. **Bug**: tombol "Jalankan MRP Ulang" pakai `router.post()` Inertia ke
   endpoint JSON murni → error. Fix: `fetch()` + `router.reload()`.
5. **Bug (kelas sama dengan `OeeGauge.vue`)**: `MrpGrid.vue` tidak
   reaktif — `ref(props.initialMrpRun)` tanpa `watch()` sama sekali. Fix:
   tambah `watch()` tanpa guard `if(val)`. **Bug turunan**: `watch` dipakai
   tapi lupa di-import dari `'vue'` — silent failure.
6. **`RopGauge.vue`**: TIDAK live-update via Echo (tidak ada broadcast
   event inventory di docs).

### Master Data CRUD (WorkCenter, Material, Product + BOM/Routing editor)
1. **`WorkCenterController`** — CRUD + `toggleActive()`, reuse `WorkCenterPolicy`.
2. **`MaterialController`** — CRUD sederhana, `MaterialPolicy` baru.
3. **`ProductController`** — CRUD + nested BOM/Routing editor dalam satu
   controller, `ProductPolicy` baru.
4. **`Pages/Products/Edit.vue`**: BOM/Routing editor inline-edit per baris.
5. **Gap ditemukan & diperbaiki**: `HandleInertiaRequests::share()` tidak
   expose `role` — fix: tambah `'role'` ke `->only(...)`.
6. **Seluruh CRUD terverifikasi end-to-end di browser.**

**Full test suite akhir sesi 2026-07-23: 102 PASS, 303 assertions, TIDAK
ADA REGRESI.**

---

## ⚠️ Koreksi Penting (2026-07-24, sesi Dashboard KPI + ExportService)

Sesi ini mengerjakan **2 fitur** dari draf ROKC: **Dashboard KPI lintas 3
engine** (selesai penuh) dan **ExportService** (selesai penuh, scope
lengkap sesuai `docs/exports.md` — PDF Jadwal, PDF OEE, Excel MRP, Excel
OEE Trend, semua 4 bagian berhasil diselesaikan dalam satu sesi).

### Dashboard KPI Lintas 3 Engine (FR-10)

1. **`/dashboard` (Breeze kosong) DIGANTI** jadi Inertia KPI Dashboard —
   keputusan diambil karena tidak ada dokumen yang mensyaratkan Breeze
   placeholder dipertahankan, dan ini pendekatan paling sesuai maksud PRD
   ("satu titik pantau lintas 3 engine").
2. **`DashboardKpiService` baru** (`app/Services/Dashboard/`) — service
   BARU, TIDAK mengubah service final manapun. Agregasi murni dari model
   (`WorkOrder`, `Schedule`, `OeeSnapshot`, `ReorderAlert`, `Material`+relasi).
   Dibuat sebagai service terpisah (bukan logic di controller) supaya
   `DashboardController` tidak perlu memanggil `InventoryController`
   (controller-manggil-controller dilarang `engineering-rules.md § 4`).
3. **`DashboardController::index()`** — thin, delegasi penuh ke
   `DashboardKpiService`. **Bug ditemukan & diperbaiki sebelum user
   test**: file sempat ke-copy ke folder salah (`app/Services/Dashboard/
   DashboardController.php`, bukan `app/Http/Controllers/`) — composer
   memberi warning PSR-4 eksplisit, langsung ketahuan, dipindah manual.
4. **Keputusan desain "makespan jadwal aktif"**: karena `schedules`
   immutable tanpa kolom status "aktif"/`is_applied` (dikonfirmasi via
   `ScheduleApplierService`, yang secara eksplisit menyatakan tidak ada
   kolom itu dan tidak boleh mengubah skema), dipakai **schedule
   terbaru** (`latest('created_at')`) sebagai proxy.
5. **Keputusan desain "WO terlambat"**: `status != 'done' AND due_date 
   today` (bukan cuma `status = 'late'`), karena tidak ada job/observer
   yang mentransisi status ke `'late'` otomatis (dikonfirmasi via grep).
6. **Keputusan desain "material stok kritis"**: dihitung REAL-TIME
   (`qty_on_hand + qty_on_order <= rop`), BUKAN baca dari tabel
   `reorder_alerts` — supaya tidak menunggu job terjadwal jam 06:00.
7. **Terverifikasi end-to-end di browser**: 3 section KPI tampil benar
   (WO Aktif 15, WO Terlambat 0, Makespan 1.182 mnt/CR, OEE kosong "belum
   ada log hari ini" — logis, Reorder Alert 0, Stok Kritis 3), Network/
   Console bersih, semua request 200.

### ExportService (scope penuh docs/exports.md — SELESAI 100%)

**Temuan krusial di awal checkpoint — blocker kompatibilitas PHP:**

`maatwebsite/excel` **TIDAK ADA VERSI STABIL yang kompatibel PHP 8.5.4**
(dikonfirmasi via `composer require` gagal + web search ke GitHub issue
`SpartnerNL/Laravel-Excel#4345` dan `PHPOffice/PhpSpreadsheet#4874` yang
ditutup "not planned"). `phpoffice/phpspreadsheet` versi yang dikunci
`maatwebsite/excel` v3.1 (`^1.30`) secara eksplisit memblokir PHP 8.5.
**Keputusan** (dikonfirmasi user, dipilih opsi paling stabil bukan
`dev-master` unreleased): pakai `phpoffice/phpspreadsheet` v5.9.0
**langsung**, tulis writer manual per sheet sesuai spesifikasi
`docs/exports.md`, tanpa dependency ke `maatwebsite/excel` sama sekali.
`barryvdh/laravel-dompdf` v3.1.2 terinstall normal, tidak ada masalah.

**Temuan teknis tambahan selama implementasi:**

1. **Root disk `local` Laravel 12 = `storage/app/private/`**, BUKAN
   `storage/app/` seperti diasumsikan `docs/exports.md` (kemungkinan
   ditulis dengan asumsi versi Laravel lama). Dikonfirmasi via
   `config/filesystems.php`. **Tidak perlu perubahan kode** — semua akses
   file lewat `Storage::disk('local')` dengan path relatif (`exports/...`)
   otomatis benar di root manapun; hanya perlu diketahui saat verifikasi
   manual (`find storage/app/private/exports/...`).
2. **dompdf `<script type="text/php">` untuk nomor halaman TIDAK BEKERJA
   secara default** — `isPhpEnabled = false` (dimatikan demi keamanan).
   Fix: pakai API resmi `Canvas::page_text()` dengan placeholder
   `{PAGE_NUM}`/`{PAGE_COUNT}` (dipanggil dari Job, bukan di Blade),
   tidak perlu mengaktifkan eksekusi PHP embedded sama sekali. Diterapkan
   konsisten di kedua PDF (Jadwal & OEE Harian).
3. **`phpoffice/phpspreadsheet` v2.0+ MENGHAPUS TOTAL API
   `*ByColumnAndRow()`** (`setCellValueByColumnAndRow()`,
   `getStyleByColumnAndRow()`, dll — 14 method, dikonfirmasi via GitHub
   issue resmi PHPOffice). Ditemukan lewat error runtime nyata
   (`Call to undefined method`), dikonfirmasi via web search karena versi
   5.9.0 jelas di luar training data. **Pengganti**: alamat sel string
   biasa (`'C5'`) via `Coordinate::stringFromColumnIndex()`, BUKAN array
   `[col,row]` (dihindari untuk konsistensi/keterbacaan).
4. **Karakter unicode (✓/⚠) tidak render di dompdf** (tampil jadi "?" —
   font Helvetica default tidak punya glyph itu). Fix: ganti ke teks
   polos ("ON TIME"/"TERLAMBAT"), tanpa simbol dekoratif.
5. **`MrpService::computeRequirements()` menghitung `release_date` tapi
   TIDAK menyimpannya ke DB** (dikonfirmasi dari komentar `MrpService`
   sendiri — migration final tidak diubah). Job Excel MRP **menghitung
   ulang** `release_date = period_date - lead_time_days` — bukan logic
   bisnis baru, murni pengurangan tanggal dari data yang sudah tersimpan
   (`period_date`, `lead_time_days`), identik dengan rumus internal
   `MrpService`.
6. **`composer audit` menunjukkan 4 advisory pada `guzzlehttp/guzzle`**
   (severity medium, terkait redirect/cookie/proxy header) — **tidak
   terkait** dependency baru (`dompdf`/`phpspreadsheet`), pre-existing di
   Laravel HTTP client. Dicatat sebagai utang teknis terpisah, tidak
   blocking untuk sesi ini.
7. **Download flow TANPA tabel DB baru**: path hasil export disimpan ke
   **Cache** (TTL 10 menit) dengan key `export:{jenis}:{identifier}:
   {userId}`, dibaca oleh endpoint status untuk polling frontend. Dipilih
   supaya TIDAK perlu migration baru (tidak ada tabel `exports` di
   `docs/database.md`, dan mengubah skema DB di luar izin sesi ini,
   konsisten dengan batasan yang sama seperti `ScheduleApplierService`).
8. **Bug ditemukan sebelum user test**: `ExportController::download()`
   awalnya bertype-hint `Illuminate\Http\Response`, padahal
   `Storage::download()` mengembalikan
   `Symfony\Component\HttpFoundation\StreamedResponse` — TypeError 500.
   Fix: ganti return type.
9. **Bug ditemukan sebelum user test**: file `resources/views/exports/
   schedule_report.blade.php` sempat memakai footer embedded PHP (lihat
   poin 2) — diperbaiki SEBELUM diminta user test, bukan setelah gagal.
10. **`routes/web.php` ParseError**: `use App\Http\Controllers\
    ExportController` tanpa titik-koma di akhir — typo manual saat copy,
    langsung ketahuan dari error message eksplisit.
11. **Desain PDF direvisi setelah feedback user** ("terlalu biasa dan
    rumit dibaca") — ditambah accent color (amber `#F59E0B` / navy
    `#0F172A`, konsisten dengan brand yang sudah dipakai `Show.vue`),
    zebra striping tabel, section header dengan border-left aksen, kartu
    ringkasan dengan card style. **User note: masih perlu modernisasi
    lebih lanjut** — lihat § Utang Teknis.

**File-file baru sesi ini:**

Backend:
- `app/Services/Dashboard/DashboardKpiService.php` (baru)
- `app/Http/Controllers/DashboardController.php` (baru)
- `app/Http/Controllers/ExportController.php` (baru — 8 method: schedulePdf,
  schedulePdfStatus, oeePdf, oeePdfStatus, mrpExcel, mrpExcelStatus,
  oeeTrendExcel, oeeTrendExcelStatus, download)
- `app/Exceptions/ExportNotAllowedException.php` (baru)
- `app/Jobs/GeneratePdfReportJob.php` (PDF Jadwal Produksi)
- `app/Jobs/GeneratePdfOeeReportJob.php` (PDF OEE Harian)
- `app/Jobs/GenerateExcelMrpReportJob.php` (Excel MRP Grid, 3 sheet)
- `app/Jobs/GenerateExcelOeeTrendReportJob.php` (Excel OEE Trend, 3 sheet)
- `resources/views/exports/schedule_report.blade.php`
- `resources/views/exports/oee_report.blade.php`

Frontend (edit, bukan file baru):
- `resources/js/Pages/Dashboard.vue` (baru — KPI cards 3 engine)
- `resources/js/Pages/Schedules/Show.vue` (+ tombol Export PDF, polling)
- `resources/js/Pages/OEE/Dashboard.vue` (+ tombol Export PDF Harian +
  Export Excel Trend, 2 date/month picker, polling)
- `resources/js/Pages/Mrp/Dashboard.vue` (+ tombol Export Excel)

Routes baru (`routes/web.php`):

GET /dashboard (diganti dari Breeze)
POST /exports/schedule/{schedule}/pdf
GET /exports/schedule/{schedule}/pdf/status
POST /exports/oee/pdf
GET /exports/oee/pdf/status
POST /exports/mrp/{mrpRun}/excel
GET /exports/mrp/{mrpRun}/excel/status
POST /exports/oee-trend/excel
GET /exports/oee-trend/excel/status
GET /exports/download

**Semua checkpoint (Dashboard KPI, PDF Jadwal + tombol, PDF OEE + tombol,
Excel MRP + tombol, Excel OEE Trend + tombol) diverifikasi end-to-end di
browser oleh user, termasuk isi file PDF/Excel diverifikasi manual
(dibuka & dicek visual, atau di-inspect terprogram untuk Excel).**

**Full test suite akhir sesi 2026-07-24: 102 PASS, 303 assertions, TIDAK
ADA REGRESI** — seluruh pekerjaan sesi ini murni service/controller/job/
route/Vue baru, tidak ada perubahan ke service final manapun
(`MrpService`, `EoqCalculatorService`, `OeeCalculatorService`,
`DowntimeAnalysisService`, `JobShopSchedulerService`, `GanttBuilderService`,
`ScheduleApplierService`).

---

## Production Environment

| Item             | Value                                            |
| ---------------- | ------------------------------------------------ |
| OS               | Ubuntu 26.04 LTS "Resolute Raccoon" via WSL2 |
| URL (dev)        | http://127.0.0.1:8000 (via `php artisan serve`)  |
| Project path     | `~/workspace/factoryos/laravel`                  |
| Queue workers    | PERMANEN via Supervisor, `/etc/supervisor/conf.d/factoryos-worker.conf` |
| Scheduler        | AKTIF via Supervisor, `/etc/supervisor/conf.d/factoryos-scheduler.conf` (`numprocs=1` wajib) |
| WebSocket server | Soketi (terpasang di sisi client, belum dijalankan — `BROADCAST_CONNECTION=log`) |
| Storage disk `local` root | **`storage/app/private/`** (default Laravel 11+, BUKAN `storage/app/` — lihat § Koreksi Penting 2026-07-24) |

### Commands

```bash
npm run build
npm run dev
php artisan serve
php artisan test
php artisan migrate
php artisan tinker
php artisan queue:failed
php artisan queue:flush
php artisan schedule:list
php artisan schedule:test
composer audit                         # cek advisory keamanan dependency
sudo supervisorctl status
sudo supervisorctl restart factoryos-worker:*
sudo supervisorctl restart factoryos-scheduler
```

**Login test users**:
- `admin@factoryos.test` / `password` (role: admin)
- `operator@factoryos.test` / `password` (role: operator)

---

## Current Build Status

### ✅ Done

**Foundation, Inertia+Sanctum, Engine 1/2/3, Master Data CRUD** — semua
selesai penuh (lihat riwayat sesi 2026-07-19 s.d. 2026-07-23 di atas).

**Dashboard KPI Lintas 3 Engine (BARU, SELESAI 2026-07-24)**
- `DashboardKpiService` (engine1Summary/engine2Summary/engine3Summary)
- `DashboardController::index()` — route `/dashboard` (menggantikan Breeze)
- `Pages/Dashboard.vue` — reuse `KpiCard.vue`
- Terverifikasi end-to-end di browser, Network/Console bersih

**ExportService (BARU, SELESAI PENUH 2026-07-24 — scope 100% docs/exports.md)**
- `barryvdh/laravel-dompdf` v3.1.2 — terinstall, dipakai untuk 2 PDF
- `phpoffice/phpspreadsheet` v5.9.0 langsung (BUKAN `maatwebsite/excel` —
  tidak kompatibel PHP 8.5.4) — dipakai untuk 2 Excel multi-sheet
- `ExportNotAllowedException` — guard sebelum dispatch job
- `GeneratePdfReportJob` — PDF Jadwal Produksi (Engine 1), tombol di
  `Schedules/Show.vue`
- `GeneratePdfOeeReportJob` — PDF OEE Harian (Engine 2), tombol di
  `OEE/Dashboard.vue`
- `GenerateExcelMrpReportJob` — Excel MRP Grid 3-sheet (Engine 3), tombol
  di `Mrp/Dashboard.vue`
- `GenerateExcelOeeTrendReportJob` — Excel OEE Trend 3-sheet (Engine 2),
  tombol di `OEE/Dashboard.vue`
- `ExportController` — 8 endpoint JSON (semua via `fetch()`, bukan
  `router.post()`) + 1 endpoint download (`StreamedResponse`)
- Download flow via Cache (TTL 10 menit), TANPA tabel DB baru
- Semua 4 jenis export diverifikasi: PDF dibuka & dicek visual manual,
  Excel di-inspect terprogram (openpyxl) untuk pastikan struktur/warna
  fill/merge cells benar

**Total: 102 test PASS (303 assertions), full suite, TIDAK ADA REGRESI
dari sesi 2026-07-24.**

### 🔄 In Progress
- (belum ada — siap mulai task baru)

### ⏳ Not Started
- `ScheduleController::show()` masih closure inline di `routes/web.php`
- **Soketi belum benar-benar dijalankan** — `BROADCAST_CONNECTION` masih `log`
- Feature test untuk `OeeController` dan `EoqCalculatorService::computeAndSave()`
- Endpoint PATCH status untuk `ReorderAlert` (utang teknis lama, perlu
  diskusi otorisasi)
- **Modernisasi visual** — Dashboard KPI, PDF export, dan komponen umum
  lain dinilai user "kurang modern" secara gaya/warna (lihat § Utang Teknis)

---

## ⚠️ Utang Teknis / Perlu Investigasi

**Status per sesi 2026-07-24: Dashboard KPI dan ExportService SEMUA
SELESAI. Daftar di bawah adalah utang teknis yang MASIH TERBUKA.**

1. `ScheduleController::show()` masih closure inline — kosmetik, bukan bug.
2. **Soketi belum benar-benar dijalankan** — `BROADCAST_CONNECTION` masih
   `log`. Ini adalah fitur #3 dari draf ROKC sesi 2026-07-23 yang belum
   dikerjakan (2 dari 3 fitur dikerjakan sesi 2026-07-24: Dashboard KPI +
   ExportService). Komponen Vue (`OeeGauge.vue`, `resources/js/echo.js`)
   sudah siap tanpa perlu ubah kode.
3. **Dashboard KPI lintas 3 engine belum dimulai** ~~→ SELESAI 2026-07-24~~
4. **Feature test untuk `OeeController` dan
   `EoqCalculatorService::computeAndSave()` belum ada.**
5. **Endpoint PATCH status `ReorderAlert` belum ada** — `AlertBanner.vue`
   punya tombol "Tandai Dilihat"/"PO Dibuat" yang 404. Sengaja tidak
   dibuat karena butuh diskusi otorisasi (role apa yang boleh
   acknowledge/order) terpisah dari scope manapun yang dikerjakan sampai
   sekarang. **Dikonfirmasi ulang sesi 2026-07-24**: ini BUKAN bug baru,
   murni utang teknis lama sesi 2026-07-23 yang belum tersentuh.
6. `e2e-production-logs.mjs` di root project — skrip diagnostik ad-hoc,
   aman dihapus.
7. **BARU (2026-07-24): `guzzlehttp/guzzle` — 4 advisory keamanan
   severity medium** (`composer audit`), terkait redirect/cookie/proxy
   header. Tidak terkait dependency ExportService, pre-existing di
   Laravel HTTP client. Perlu `composer update guzzlehttp/guzzle` ke
   `>=7.15.1`, dicek terpisah (belum dilakukan sesi ini, di luar scope).
8. **BARU (2026-07-24): Modernisasi visual/gaya** — user secara eksplisit
   menyatakan tampilan Dashboard KPI (`Pages/Dashboard.vue`, `KpiCard.vue`)
   dan hasil export PDF (`schedule_report.blade.php`, `oee_report.blade.php`)
   "kurang modern secara gaya/bentuk/warna", meski FUNGSIONAL sudah benar
   dan lengkap. Ditunda untuk sesi polish terpisah — perlu diskusi arah
   desain (mis. baca `frontend-design` skill kalau relevan untuk revisi
   berikutnya). **Bukan bug, murni preferensi estetika yang belum
   dieksekusi.**
9. **`ScheduleController::show()` closure inline** (poin 1, dipertahankan
   untuk referensi historis).

**Item yang SUDAH TERTUTUP sesi 2026-07-24 (referensi historis, dipertahankan):**
- ~~Dashboard KPI lintas 3 engine belum dimulai~~ → **SELESAI**
- ~~`ExportService` belum dimulai (dompdf & Excel library belum di-`composer require`)~~ → **SELESAI PENUH** (PDF Jadwal, PDF OEE, Excel MRP, Excel OEE Trend)

**Item yang SUDAH TERTUTUP sesi-sesi sebelumnya (referensi historis):**
- ~~Queue worker FactoryOS tidak permanen~~ → **SELESAI** (2026-07-22)
- ~~`Schedules/Show.vue` bug 500 error~~ → **SELESAI** (2026-07-22)
- ~~`MrpController` belum ada~~ → **SELESAI** (2026-07-22)
- ~~`CheckReorderAlertsJob` belum dijadwalkan otomatis~~ → **SELESAI** (2026-07-22)
- ~~Data seeder Engine 3 minim~~ → **SELESAI** (2026-07-22)
- ~~Frontend MRP belum dimulai~~ → **SELESAI** (2026-07-23)
- ~~Master data CRUD belum ada UI/Controller~~ → **SELESAI** (2026-07-23)
- ~~`HandleInertiaRequests` tidak expose `role`~~ → **SELESAI** (2026-07-23)

---

## Koreksi Dokumen (formula & lain-lain)

`docs/oee-formulas.md` dan `docs/engineering-rules.md` sebelumnya
menyatakan hasil OEE contoh manual = 0.771099. **Ini salah hitung.**
Hasil benar: 0.875000 × 0.904762 × 0.973684 = **0.770833**. Sudah
dikoreksi di kedua file docs & semua test terkait.

`docs/prd.md` menyatakan OS environment "Ubuntu 24.04 LTS" — realita
`VERSION_ID="26.04"` ("Resolute Raccoon"). Perlu dikoreksi manual oleh
pemilik project di source docs (read-only dari sisi sesi Claude).

**BARU (2026-07-24)**: `docs/exports.md` menyebut library
`maatwebsite/excel` dan path `storage/app/exports/` — **keduanya sudah
tidak akurat**:
- `maatwebsite/excel` diganti `phpoffice/phpspreadsheet` langsung (tidak
  ada versi stabil `maatwebsite/excel` yang kompatibel PHP 8.5.4).
- Path fisik penyimpanan adalah `storage/app/private/exports/` di
  Laravel 12 (root disk `local` berubah sejak Laravel 11), meski kode
  tetap menulis path relatif `exports/...` (abstraksi `Storage::disk()`
  menangani ini otomatis, tidak ada perubahan kode yang diperlukan).

Perlu dikoreksi manual oleh pemilik project di `docs/exports.md`
(read-only dari sisi sesi Claude).

---

## Arsitektur Tiga Engine + Dashboard/Export

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
- Engine 1 → `ScheduleCreated` event → Engine 3 (`RunMrpJob`) — otomatis
  via queue worker permanen
- Engine 3 → `CheckReorderAlertsJob` otomatis tiap hari 06:00
- **Dashboard KPI** → agregasi read-only dari ketiga engine, tanpa logic
  kalkulasi baru (semua dari service final)
- **ExportService** → PDF/Excel dari data yang sudah dihitung ketiga
  engine (Schedule/OeeSnapshot/MrpRun), tidak menghitung ulang formula
  apapun, murni presentasi/laporan

---

## Documentation

| File                        | Baca ketika mengerjakan...                           |
| --------------------------- | ---------------------------------------------------- |
| `docs/scheduling.md`        | Engine 1: JSSP, dispatching rules, Gantt             |
| `docs/oee-formulas.md`      | Engine 2: OEE, Pareto, downtime                      |
| `docs/inventory.md`         | Engine 3: EOQ, Safety Stock, ROP, MRP                |
| `docs/database.md`          | Migrations, models, queries, schema, indexing        |
| `docs/architecture.md`      | Services, observers, jobs, events, queues, WebSocket |
| `docs/gantt.md`             | D3.js Gantt: data format, interaction, SVG layout    |
| `docs/exports.md`           | PDF, Excel generation per engine (**path & library sudah tidak akurat, lihat § Koreksi Dokumen**) |
| `docs/engineering-rules.md` | Presisi, bcmath, business rules, testing policy      |

**Catatan**: beberapa hal di dokumen ternyata tidak sesuai implementasi
nyata. **Selalu verifikasi keberadaan file/class/package dengan
`find`/`cat`/`composer show` sebelum berasumsi sesuatu sudah ada/kompatibel,
sekalipun didokumentasikan** — sesi 2026-07-24 adalah contoh nyata
terbaru: `maatwebsite/excel` diasumsikan bisa diinstall langsung, ternyata
diblokir total oleh PHP 8.5.4, baru ketahuan setelah `composer require`
gagal dengan pesan error panjang.

---

## Main Services

| Service                   | Tanggung Jawab                                     | Status       |
| ------------------------- | --------------------------------------------------- | ------------ |
| `JobShopSchedulerService` | Jalankan 4 dispatching rules, simpan schedule       | ✅ Selesai (final) |
| `GanttBuilderService`     | Transform assignments → D3.js-ready dataset         | ✅ Selesai (final) |
| `ScheduleApplierService`  | Terapkan schedule terpilih ke wo_operations         | ✅ Selesai (final) |
| `WoOperationGeneratorService` | Generate wo_operations dari routing            | ✅ Selesai (final) |
| `OeeCalculatorService`    | Hitung OEE, trend data, benchmark vs world class    | ✅ Selesai (final) |
| `DowntimeAnalysisService` | Pareto analysis downtime (agregat cross-log)        | ✅ Selesai (final) |
| `EoqCalculatorService`    | EOQ, Safety Stock, ROP, Total Annual Cost (bcmath)  | ✅ Selesai (final) |
| `MrpService`              | MRP explosion: schedule → material requirements     | ✅ Selesai (final) |
| **`DashboardKpiService`** | **Agregasi KPI lintas 3 engine (BARU 2026-07-24)**  | ✅ Selesai   |

> **Service "final" = TIDAK BOLEH diubah logic internalnya di sesi
> manapun tanpa diskusi eksplisit.** `DashboardKpiService` BUKAN "final"
> dalam artian ini — ia murni agregasi read-only dari data engine lain,
> boleh diperluas kalau ada KPI baru diminta, tapi tetap TIDAK boleh
> menduplikasi/menghitung ulang formula engineering (OEE/EOQ/dst).

---

## Main Controllers

| Controller | Tanggung Jawab | Status |
|---|---|---|
| `WorkOrderController` | CRUD WO + generate operations + status transition | ✅ Selesai |
| `ScheduleController` | run/compareAll/ganttData/apply | ✅ Selesai |
| `ProductionLogController`, `DowntimeController` | CRUD log produksi + downtime | ✅ Selesai |
| `OeeController` | dashboard/pareto/trend/benchmark | ✅ Selesai |
| `MrpController` | run/show/alerts (JSON) + dashboard() (Inertia) | ✅ Selesai |
| `InventoryController` | status() — read-only inventory vs ROP | ✅ Selesai |
| `WorkCenterController` | CRUD + toggleActive() | ✅ Selesai |
| `MaterialController` | CRUD | ✅ Selesai |
| `ProductController` | CRUD + nested BOM/Routing editor | ✅ Selesai |
| **`DashboardController`** | **index() — KPI ringkasan lintas 3 engine (BARU)** | ✅ Selesai |
| **`ExportController`** | **8 endpoint export (PDF/Excel x4 jenis) + download (BARU)** | ✅ Selesai |

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
Quality=0.973684, OEE=0.770833.

**ENGINE 3 — INVENTORY**
EOQ = √(2 × D × S / H)
Safety Stock = Z × σ_d × √(LT)
ROP = (avg_daily_demand × LT) + Safety Stock
Net Req(t) = max(0, GrossReq(t) - ProjOnHand(t-1) - ScheduledReceipts(t))
Planned Order Release = roundUpToEoq(Net Req(t))

Contoh manual tervalidasi (`EoqCalculatorServiceTest`):
D=1200, S=150000, H=5000 → EOQ=268.328157;
Z=1.6450, σ_d=3, LT=7 → Safety Stock=13.056783, ROP=83.056783.

Contoh manual tervalidasi (`MrpServiceTest`):
on-hand=50 + SR=100 di t1 → tidak ada Net Requirement;
on-hand=10, GR=30 di t2 → NR=20 → roundUpToEoq(20,100)=100.

Reorder Alert tervalidasi (`MrpServiceTest`):
qty_on_hand=20 + qty_on_order=0 ≤ rop=38.5477 → 1 alert 'open' dibuat.

**DASHBOARD KPI (BARU 2026-07-24)**
WO Aktif = count(status IN [draft, scheduled, in_progress])
WO Terlambat = count(status != 'done' AND due_date < today)
Makespan Aktif = Schedule::latest('created_at')->makespan_minutes
Avg OEE Hari Ini = rata-rata bcmath oee lintas snapshot hari ini
Material Stok Kritis = count(qty_on_hand + qty_on_order <= rop), real-time

**EXPORT SERVICE (BARU 2026-07-24)**
Semua nilai di PDF/Excel diambil LANGSUNG dari data tersimpan
(Schedule/ScheduleAssignment, OeeSnapshot/ProductionLog, MrpRequirement) —
TIDAK ADA kalkulasi baru. Satu pengecualian: `release_date` di sheet
"Planned Order Releases" dihitung ulang (`period_date - lead_time_days`)
karena `MrpService` menghitungnya tapi tidak menyimpannya ke DB.

---

## Catatan Teknis Penting (pelajaran dari sesi-sesi sebelumnya)

- **bcmath tidak pernah membulatkan**, selalu truncate. Pakai helper
  `round()`/`roundSigned()` manual — salin pola dari service yang sudah ada.
- **Cast model vs scale bcmath internal BISA BERBEDA** — selalu cek
  `protected $casts` model sebenarnya sebelum menulis assertion test.
- **Laravel 12 tidak pakai `EventServiceProvider` bawaan** — event/listener
  diregister manual di `AppServiceProvider::boot()`.
- **Laravel 12 tidak punya `app/Console/Kernel.php`** — scheduled task
  didaftarkan di `routes/console.php`.
- **Laravel 12 root disk `local` = `storage/app/private/`** (BARU
  2026-07-24), bukan `storage/app/` — tidak perlu ubah kode
  (`Storage::disk('local')` abstraksi otomatis), tapi penting diketahui
  saat verifikasi manual filesystem.
- **Sebuah Event/Listener/Service/Controller/Package yang disebut di
  dokumen belum tentu benar-benar ada/kompatibel** — selalu verifikasi
  dengan `find`/`cat`/`composer show`/`composer require` (dry-run mental)
  sebelum berasumsi. Kasus terbaru (2026-07-24): `maatwebsite/excel`
  diasumsikan bisa dipasang, ternyata total tidak kompatibel PHP 8.5.4.
- **Package versi sangat baru (rilis dalam beberapa minggu terakhir) bisa
  punya breaking API changes di luar training data Claude** — WAJIB
  web search ke sumber resmi (GitHub issues/discussions, changelog)
  sebelum menebak signature method, JANGAN asumsi dari memori pelatihan.
  Kasus nyata: `phpoffice/phpspreadsheet` v5.9.0 sudah menghapus total
  API `*ByColumnAndRow()` sejak v2.0 (2022) — training data lama masih
  menganggap API itu valid.
- **dompdf menonaktifkan eksekusi PHP embedded (`<script type="text/php">`)
  secara default** (`isPhpEnabled = false`, demi keamanan) — untuk nomor
  halaman gunakan `Canvas::page_text()` resmi dari kode PHP (Job), bukan
  ditulis di Blade.
- **dompdf tidak render karakter unicode dekoratif** (✓/⚠ dst, tampil
  jadi "?") pada font default — pakai teks polos untuk badge status.
- **`bootstrap/app.php`**: `withRouting(channels: ...)` sudah cukup untuk
  `routes/channels.php`. Jangan tambahkan `withBroadcasting()` juga.
- **`bootstrap/app.php` middleware**: `HandleInertiaRequests` via
  `$middleware->web(append: [...])`; Sanctum via `$middleware->api(prepend: [...])`.
- **Route statis vs wildcard**: path statis WAJIB didaftarkan SEBELUM
  wildcard dengan pola sama — diterapkan konsisten di seluruh
  `/schedules/*`, `/mrp/*`, `/products/*`, dan `/exports/*` (BARU
  2026-07-24: `/exports/schedule/{schedule}/pdf/status` didaftarkan
  setelah `/exports/schedule/{schedule}/pdf` — tidak konflik karena
  jumlah segmen beda, tapi tetap berurutan untuk keterbacaan).
- **`JobShopSchedulerService::compareAll()` mengembalikan array
  ASOSIATIF** — WAJIB `array_values()` sebelum dikirim sebagai prop Inertia.
- **Policy di Laravel 12** auto-discovered dari nama file `{Model}Policy`
  di `app/Policies/` — tidak perlu register manual.
- **Test dengan Observer aktif** di `QUEUE_CONNECTION=sync`: isolasi
  dengan `Event::fake([...])` di `setUp()` kalau perlu.
- **Query rentang tanggal WAJIB pakai `whereDate()`**, bukan `whereBetween()`.
- **Event Echo dengan `broadcastAs()` custom**: listener client WAJIB
  pakai titik di depan (`.oee.updated`, bukan `oee.updated`).
- **Env var fallback JS**: `??` TIDAK menangkap string kosong `""`.
- **Watcher Vue pada prop yang bisa berubah setelah mount**: JANGAN pakai
  guard `if (val) target.value = val` — selalu sinkronkan tanpa syarat.
  Prop reactivity untuk objek yang bisa diganti utuh oleh parent SELALU
  butuh `watch()` eksplisit di child jika child menyimpan salinan lokal
  via `ref()`. **PENGECUALIAN (BARU 2026-07-24)**: kalau parent (top-level
  Inertia page component) langsung memakai `props.xxx` tanpa menyalin ke
  `ref()` lokal (seperti `Mrp/Dashboard.vue` untuk `latestMrpRunId`),
  TIDAK perlu `watch()` tambahan — prop Inertia otomatis reaktif setelah
  `router.reload()`. `watch()` eksplisit hanya wajib kalau ada salinan
  state lokal via `ref(props.xxx)`.
- **Import Vue Composition API yang dipakai tapi lupa di-import**
  (`watch`, `onMounted`, dll.) menyebabkan silent failure di production
  build — selalu cek `import { ... } from 'vue'` mencakup semua composable
  yang dipakai.
- **Endpoint controller yang mengembalikan `response()->json(...)` murni
  TIDAK BOLEH dipanggil dari frontend pakai `router.post()`/`router.get()`
  Inertia** — gunakan `fetch()` biasa. Pola ini diterapkan KONSISTEN di
  seluruh endpoint export sesi 2026-07-24 (semua 4 jenis export + status
  polling pakai `fetch()`, bukan Inertia router).
- **Kolom shared props Inertia (`HandleInertiaRequests::share()`)** adalah
  satu-satunya sumber data yang tersedia di SEMUA halaman Vue tanpa
  eksplisit di-pass per controller.
- **Model Eloquent WAJIB override `$table` eksplisit** kalau nama tabel
  migration tidak mengikuti konvensi plural default Laravel.
- **`$fillable` yang sengaja tidak menyertakan `created_at`** (tabel
  immutable): WAJIB `forceFill([...])->save()`, bukan `create([...])`.
- **Proses lain di `ps aux` dengan nama command sama bisa jadi milik
  project LAIN** — selalu verifikasi working directory.
- **Seeder factory tidak otomatis menjalankan efek samping controller** —
  cek dan tiru manual di seeder jika perlu.
- **Verifikasi "selesai" harus end-to-end**: unit/feature test PASS tidak
  menjamin frontend bisa diakses/reaktif dengan benar di browser, ATAU
  bahwa file export (PDF/Excel) benar-benar terbentuk dengan isi yang
  benar. **Sesi 2026-07-24 konsisten menerapkan ini**: setiap PDF dibuka
  & dicek visual manual oleh user, setiap Excel di-inspect terprogram
  (Python `openpyxl`) untuk verifikasi struktur sheet/merge cells/fill
  color sebelum ditandai selesai.
- **Background job untuk file generation (PDF/Excel) butuh mekanisme
  "tahu kapan selesai" di frontend** — karena job async, gunakan Cache
  (bukan tabel DB baru kalau tidak ada izin migration) untuk simpan path
  hasil, lalu polling ringan (`setInterval` 2 detik, timeout ~30 detik)
  dari frontend ke endpoint status. Pola ini diterapkan identik di semua
  4 jenis export sesi 2026-07-24 — replikasi pola ini untuk export baru
  di masa depan.
- **Polling timer (`setInterval`) WAJIB dibersihkan di `onBeforeUnmount`**
  — kalau user pindah halaman saat polling berlangsung, timer tetap
  jalan di background kalau tidak di-`clearInterval()`.

---

## Roadmap per Phase

### Phase 1 — Foundation ✅ SELESAI PENUH
### Phase 2 — Engine 1: Scheduler ✅ SELESAI PENUH (kecuali `ScheduleController::show()` closure kosmetik)
### Phase 3 — Engine 2: OEE ✅ SELESAI (kecuali Soketi live)
### Phase 4 — Engine 3: Inventory ✅ SELESAI PENUH (kecuali PATCH alert status & feature test EOQ)

### Phase 5 — Integration & Polish (Week 9–10)
- [x] Master data CRUD + BOM/Routing editor — SELESAI 2026-07-23
- [x] Role-based UI hiding — SELESAI 2026-07-23
- [x] **Dashboard KPI lintas 3 engine — SELESAI 2026-07-24**
- [x] **Export PDF & Excel per engine — SELESAI PENUH 2026-07-24**
      (PDF Jadwal, PDF OEE, Excel MRP 3-sheet, Excel OEE Trend 3-sheet)
- [ ] Soketi aktivasi nyata (`BROADCAST_CONNECTION=pusher`)
- [ ] Full test suite + canonical seeder review
- [ ] Endpoint PATCH status ReorderAlert (perlu diskusi otorisasi)
- [ ] Feature test `OeeController` & `EoqCalculatorService::computeAndSave()`
- [ ] **Modernisasi visual** (Dashboard KPI, PDF export — utang teknis baru)
- [ ] `composer update guzzlehttp/guzzle` (4 advisory keamanan medium)

---

## Urutan Kerja Per Sesi

1. Update `Current Build Status` di file ini
2. Baca docs yang relevan — cross-check dengan kode nyata kalau ragu,
   verifikasi keberadaan file/class/namespace/PACKAGE dengan
   `find`/`cat`/`composer show` sebelum asumsi, sekalipun didokumentasikan.
3. **Untuk package versi sangat baru (rilis beberapa minggu terakhir):
   web search ke sumber resmi (GitHub) untuk breaking API changes**
   sebelum menulis kode yang bergantung padanya — training data bisa
   sudah usang untuk versi sebaru itu.
4. Queue worker & scheduler PERMANEN via Supervisor — verifikasi
   `sudo supervisorctl status` di awal sesi.
5. migration → model → factory → service → controller → Vue page
6. Unit test setiap Service baru sebelum lanjut
7. **Verifikasi end-to-end di browser DAN di database/file nyata** —
   jangan cuma andalkan `php artisan test`. Untuk file yang di-generate
   (PDF/Excel), buka & cek isinya secara visual/terprogram, bukan cuma
   pastikan file-nya ada.
8. `php artisan test` (full suite) sebelum selesai sesi — pastikan tidak
   ada regresi
9. Catat temuan/bug/utang teknis baru di § Utang Teknis
10. **Jangan tulis `claude.md` final sebelum user eksplisit mengonfirmasi
    verifikasi browser/file untuk checkpoint TERAKHIR sesi.**

---

## Prompt Sesi Berikutnya (Draf ROKC — Fitur Baru)

Dashboard KPI dan ExportService (scope penuh) dari draf sesi lalu SUDAH
TUNTAS 100%. Kandidat fokus sesi berikutnya:

**PERAN:**
Senior full-stack engineer Laravel 12 + Inertia v3 + Vue 3, berpengalaman
dengan WebSocket production-grade (Soketi/Pusher protocol). Selalu
verifikasi dulu (`find`/`cat`/`composer show`/browser Network tab)
sebelum menulis atau melanjutkan kode.

**OBJEKTIF (prioritas urut):**

1. **Soketi aktivasi nyata** (satu-satunya item besar tersisa dari draf
   3 fitur sesi 2026-07-23) — ganti `BROADCAST_CONNECTION=log` jadi
   `pusher` di `.env`, isi `VITE_PUSHER_APP_KEY`/`VITE_PUSHER_HOST`/dst
   sesuai `config/broadcasting.php`, jalankan `npx soketi start` (cek
   dulu apakah `@soketi/soketi` sudah terdaftar di `package.json` atau
   perlu `npm install -g`). Komponen Vue (`OeeGauge.vue`,
   `resources/js/echo.js`) SUDAH SIAP tanpa ubah kode. Verifikasi: submit
   production log di tab browser A (user berbeda), lihat `OeeGauge.vue`
   update otomatis di tab B tanpa refresh, cek Network tab bagian WS.

2. **Item kecil yang bisa diselipkan kapan saja:**
   - Endpoint `PATCH /mrp/alerts/{id}/status` — perlu diskusi otorisasi
     dulu (role apa yang boleh acknowledge/order) sebelum menulis kode.
   - `ScheduleController::show()` closure inline → method controller
     sesungguhnya (kosmetik).
   - Feature test `EoqCalculatorService::computeAndSave()` (butuh
     `RefreshDatabase`) dan `OeeController`.
   - `composer update guzzlehttp/guzzle` ke `>=7.15.1` (4 advisory
     keamanan medium, tidak urgent tapi sebaiknya dibereskan).
   - **Modernisasi visual** Dashboard KPI + PDF export — user menilai
     "kurang modern secara gaya/warna". Perlu diskusi arah desain di
     awal sesi (skema warna, tipografi) sebelum eksekusi; pertimbangkan
     baca skill `frontend-design` kalau relevan untuk styling Vue.

**KONTEKS PENTING:**
- **`maatwebsite/excel` TIDAK DIPAKAI** di project ini (tidak kompatibel
  PHP 8.5.4) — semua Excel export pakai `phpoffice/phpspreadsheet`
  langsung. API `*ByColumnAndRow()` sudah dihapus sejak v2.0 — pakai
  alamat sel string (`'C5'`) via `Coordinate::stringFromColumnIndex()`.
- **Root disk `local` = `storage/app/private/`**, bukan `storage/app/`.
- Pola download-flow-via-Cache (bukan tabel DB) sudah established untuk
  4 jenis export — replikasi kalau ada export baru.
- Pola `fetch()` vs `router.post()` untuk endpoint JSON vs Inertia sudah
  established secara konsisten di seluruh project.
- `MrpService`, `EoqCalculatorService`, `OeeCalculatorService`,
  `DowntimeAnalysisService`, `JobShopSchedulerService`,
  `GanttBuilderService`, `ScheduleApplierService` = **FINAL, JANGAN
  DIUBAH**. `DashboardKpiService` boleh diperluas untuk KPI baru (bukan
  "final" dalam artian yang sama), tapi tetap tidak boleh menghitung
  ulang formula engineering apapun.

**BATASAN:**
- Checkpoint per komponen: tunjukkan kode → user test → user konfirmasi
  eksplisit → baru lanjut.
- JANGAN ubah logic Service manapun yang sudah final.
- SETIAP klaim "sudah ada"/"sudah terinstall"/"kompatibel" WAJIB
  diverifikasi dengan command nyata sebelum dipakai sebagai asumsi.
- Untuk package versi sangat baru, WAJIB web search ke sumber resmi
  sebelum menebak API — jangan andalkan training data untuk hal sespesifik
  versi rilis mingguan.
- JANGAN tulis `claude.md` final sebelum user eksplisit mengonfirmasi
  hasil verifikasi browser/file untuk checkpoint TERAKHIR sesi.
- Di akhir sesi: jalankan `php artisan test` (harus tetap 102 test PASS,
  tidak ada regresi), tulis ringkasan maksimal 8 baris, sertakan FILE
  UTUH `claude.md` untuk ditimpa, termasuk draf ROKC baru.
- Jawaban tetap Bahasa Indonesia; kode/nama variabel/komentar teknis
  mengikuti `docs/engineering-rules.md § 5` (kode=English, UI=Indonesia,
  komentar boleh Indonesia untuk penjelasan engineering).

Ringkasan sesi ini (8 baris):

Dashboard KPI lintas 3 engine selesai penuh — DashboardKpiService baru, /dashboard diganti dari Breeze ke Inertia KPI.
ExportService selesai 100% — PDF Jadwal, PDF OEE, Excel MRP 3-sheet, Excel OEE Trend 3-sheet, semua + tombol UI + polling.
Temuan krusial: maatwebsite/excel tidak kompatibel PHP 8.5.4 — diganti phpoffice/phpspreadsheet v5.9.0 langsung (API *ByColumnAndRow() sudah dihapus sejak v2.0, dikonfirmasi via web search).
Bug ditemukan & diperbaiki sebelum/selama user test: file controller salah lokasi, routes/web.php ParseError (titik-koma), download() return type salah, dompdf embedded-PHP footer tidak jalan (diganti Canvas::page_text()), karakter unicode tidak render.
Semua service final tidak disentuh sama sekali.
102 test PASS, 303 assertions, tidak ada regresi.
Semua checkpoint diverifikasi end-to-end: browser (Dashboard, tombol export) + isi file (PDF dibuka manual, Excel di-inspect via Python openpyxl).
Utang teknis baru: Soketi (fitur belum dikerjakan), modernisasi visual (Dashboard+PDF dinilai kurang modern), guzzle 4 advisory keamanan.