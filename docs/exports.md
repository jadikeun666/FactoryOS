# docs/exports.md — PDF & Excel Export

## Library

| Format | Library | Install |
|---|---|---|
| PDF | `barryvdh/laravel-dompdf` | `composer require barryvdh/laravel-dompdf` |
| Excel | `maatwebsite/laravel-excel` | `composer require maatwebsite/excel` |

> JANGAN upgrade `maatwebsite/excel` tanpa cek kompatibilitas phpoffice/phpspreadsheet
> dengan versi PHP yang digunakan.

---

## Export Guard

Export hanya boleh dilakukan jika ada schedule yang sudah di-apply dan data OEE tersedia.
Ini di-enforce di `ExportController` sebelum dispatch job.

```php
// app/Exceptions/ExportNotAllowedException.php
class ExportNotAllowedException extends HttpException {
    public function __construct(string $reason) {
        parent::__construct(403, "Export tidak diizinkan: {$reason}");
    }
}

// app/Http/Controllers/ExportController.php
public function schedulePdf(Schedule $schedule): JsonResponse
{
    if (!$schedule->assignments()->exists()) {
        throw new ExportNotAllowedException('Schedule belum memiliki assignments');
    }
    GeneratePdfReportJob::dispatch($schedule, auth()->id());
    return response()->json(['message' => 'Export sedang diproses']);
}
```

---

## PDF Reports

### 1. Laporan Jadwal Produksi (Engine 1)

**Template:** `resources/views/exports/schedule_report.blade.php`

**Konten:**
- Header: nama pabrik, tanggal generate, periode jadwal, algoritma yang dipakai
- Tabel summary: Makespan, Total Tardiness, Late WO Count, Mean Flow Time
- Tabel detail per Work Order:
  - Kolom: No WO, Produk, Qty, Due Date, Release Date, Status (On Time / Terlambat)
  - Row highlight merah jika terlambat
- Tabel operasi per mesin:
  - Kolom: Mesin, WO, Operasi ke-, Mulai, Selesai, Durasi
  - Diurutkan per mesin, per waktu mulai
- Footer: halaman X dari Y, timestamp generate

**Job:**
```php
// app/Jobs/GeneratePdfReportJob.php (untuk jadwal)
public function handle(): void
{
    $data = [
        'schedule'    => $this->schedule->load('assignments.woOperation.workOrder.product', 'assignments.workCenter'),
        'generated_at' => now(),
        'user'        => User::find($this->userId),
    ];

    $pdf = Pdf::loadView('exports.schedule_report', $data)
        ->setPaper('a4', 'landscape');

    $filename = "schedule_{$this->schedule->id}_{now()->format('Ymd_His')}.pdf";
    $path = "exports/{$filename}";

    Storage::disk('local')->put($path, $pdf->output());

    ExportGenerated::dispatch($this->schedule->id, $this->userId, 'pdf', $path);
}
```

---

### 2. Laporan OEE Harian (Engine 2)

**Template:** `resources/views/exports/oee_report.blade.php`

**Konten:**
- Header: tanggal laporan, filter mesin (atau semua)
- Tabel OEE per mesin per shift:
  - Kolom: Mesin, Shift, Planned (mnt), Downtime (mnt), Output, Good Output, Availability, Performance, Quality, OEE
  - Row highlight: OEE < 60% → merah, 60–85% → kuning, ≥ 85% → hijau
- Ringkasan Pareto downtime dalam periode:
  - Tabel: Kategori, Total Downtime (mnt), Persentase, Kumulatif
- Footer: timestamp, user yang generate

---

## Excel Reports

### 1. MRP Grid (Engine 3)

**Menggunakan:** Multiple sheets via `WithMultipleSheets`

**Sheet 1 — Ringkasan MRP Run**
```php
// app/Exports/Sheets/MrpSummarySheet.php
// Kolom: Material, On Hand, On Order, EOQ, Safety Stock, ROP, Status
```

**Sheet 2 — Grid per Material**
```php
// app/Exports/Sheets/MrpGridSheet.php
// Baris: satu baris per material
// Kolom: Material, [Periode t=1], [Periode t=2], ..., [Periode t=N]
// Setiap cell berisi: GR | SR | POH | NR (atau sub-baris jika muat)
// Highlight NR > 0 dengan warna kuning
// Highlight Planned Order Release dengan warna biru
```

**Sheet 3 — Planned Order Releases**
```php
// app/Exports/Sheets/PlannedOrderSheet.php
// Kolom: Material, SKU, Qty Order, Tanggal Order Keluar, Lead Time, Est. Tiba
// Diurutkan by tanggal order ascending
```

```php
// app/Exports/MrpExport.php
class MrpExport implements WithMultipleSheets {
    public function sheets(): array {
        return [
            new MrpSummarySheet($this->mrpRun),
            new MrpGridSheet($this->mrpRun),
            new PlannedOrderSheet($this->mrpRun),
        ];
    }
}
```

---

### 2. OEE Trend Bulanan (Engine 2)

**Sheet 1 — Data OEE**
```
Kolom: Tanggal, Mesin, Shift, Availability, Performance, Quality, OEE
Satu baris per snapshot
```

**Sheet 2 — Ringkasan per Mesin**
```
Kolom: Mesin, Avg OEE, Avg Availability, Avg Performance, Avg Quality, Total Downtime (jam)
```

**Sheet 3 — Pareto Downtime**
```
Kolom: Kategori, Total Menit, Persentase, Kumulatif
```

---

## ExportService — Orkestrasi

```php
// app/Services/ExportService.php
class ExportService {
    public function dispatchPdfSchedule(Schedule $schedule, int $userId): void;
    public function dispatchPdfOee(Carbon $from, Carbon $to, ?int $workCenterId, int $userId): void;
    public function dispatchExcelMrp(MrpRun $mrpRun, int $userId): void;
    public function dispatchExcelOeeTrend(Carbon $from, Carbon $to, int $userId): void;

    public function getDownloadUrl(string $filePath): string;
    // Generate temporary signed URL untuk download file dari storage
}
```

---

## Download Flow

```
User klik "Export PDF"
  → POST /exports/schedule/{id}/pdf
  → ExportController::schedulePdf()
  → guard check
  → GeneratePdfReportJob::dispatch()
  → Response: { message: "Export sedang diproses", job_id: "..." }

Job selesai:
  → ExportGenerated event dispatch
  → (opsional) broadcast ke user via private channel

User klik "Download"
  → GET /exports/download?path=exports/schedule_1_20240115.pdf
  → ExportController::download()
  → return Storage::download($path)
```

---

## Lokasi File Export

```
storage/app/exports/
├── schedule_1_20240115_143022.pdf
├── oee_report_20240115_143500.pdf
├── mrp_run_5_20240115_144000.xlsx
└── oee_trend_202401_144500.xlsx
```

File lama (> 7 hari) dibersihkan via scheduled command:
```php
$schedule->command('exports:cleanup --days=7')->daily()->at('02:00');
```
