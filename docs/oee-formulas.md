# docs/oee-formulas.md — Engine 2: OEE & Downtime Analytics

## Referensi

- ISO 22400-2:2014 — *Automation systems and integration — Key performance indicators (KPIs) for manufacturing operations management — Part 2: Definitions and descriptions*
- Nakajima, S. (1988). *Introduction to TPM: Total Productive Maintenance*. Productivity Press.
  → Sumber konsep OEE original dan Six Big Losses
- Hansen, R.C. (2001). *Overall Equipment Effectiveness*. Industrial Press.
  → Implementasi praktis OEE di pabrik

---

## Formula OEE (ISO 22400 Compliant)

```
OEE = Availability × Performance × Quality
```

### Availability
```
Availability = Operating Time / Planned Production Time
             = (Planned_minutes - Downtime_minutes) / Planned_minutes

Range: 0.0 – 1.0
World class: ≥ 0.90
Mengukur: apakah mesin berjalan saat seharusnya berjalan?
```

### Performance
```
Performance = Actual Output / Theoretical Maximum Output
            = (Actual_output × Ideal_cycle_time_minutes) / Operating_time

WAJIB di-cap pada 1.0 — tidak bisa melebihi 100%
Jika hasil > 1.0, simpan sebagai 1.0 (bukan error data, tapi rounding)

Range: 0.0 – 1.0
World class: ≥ 0.95
Mengukur: apakah mesin berjalan di kecepatan penuh?
```

### Quality
```
Quality = Good Output / Total Actual Output
        = good_output / actual_output

Range: 0.0 – 1.0
World class: ≥ 0.9999 (99.99%)
Mengukur: apakah output memenuhi standar kualitas?
```

### OEE Benchmarks
```
OEE ≥ 85%  → World Class (target industri manufaktur)
OEE ~60%   → Typical (rata-rata pabrik)
OEE < 40%  → Low → investigasi segera, ada masalah sistemik
```

---

## Implementasi bcmath (WAJIB)

```php
// app/Services/OEE/OeeCalculatorService.php

public function compute(ProductionLog $log): OeeSnapshot
{
    $scale = 6;

    $planned  = (string) $log->planned_minutes;
    $downtime = (string) $log->downtime_minutes;
    $output   = (string) $log->actual_output;
    $good     = (string) $log->good_output;
    $ict      = (string) $log->ideal_cycle_time_minutes;

    $operating = bcsub($planned, $downtime, $scale);

    // Guard: planned = 0
    if (bccomp($planned, '0', $scale) === 0) {
        throw new InvalidProductionLogException('Planned minutes tidak boleh 0');
    }

    // Guard: output = 0
    if (bccomp($output, '0', $scale) === 0) {
        throw new InvalidProductionLogException('Actual output tidak boleh 0');
    }

    // Availability
    $availability = bcdiv($operating, $planned, $scale);

    // Performance (cap di 1.0)
    $theoretical_max = bcdiv(
        bcmul($output, $ict, $scale),
        $operating,
        $scale
    );
    $performance = bccomp($theoretical_max, '1.000000', $scale) > 0
        ? '1.000000'
        : $theoretical_max;

    // Quality
    $quality = bcdiv($good, $output, $scale);

    // OEE
    $oee = bcmul(bcmul($availability, $performance, $scale), $quality, $scale);

    return OeeSnapshot::updateOrCreate(
        [
            'work_center_id' => $log->work_center_id,
            'log_date'       => $log->log_date,
            'shift_id'       => $log->shift_id,
        ],
        [
            'availability' => $availability,
            'performance'  => $performance,
            'quality'      => $quality,
            'oee'          => $oee,
            'computed_at'  => now(),
        ]
    );
}
```

---

## Contoh Perhitungan Manual

```
Data produksi shift pagi, Mesin M1, 2024-01-15:
  planned_minutes          = 480 (8 jam shift)
  downtime_minutes         = 60 (1 jam breakdown)
  actual_output            = 380 unit
  good_output              = 370 unit
  ideal_cycle_time_minutes = 1.0 menit/unit

Operating Time = 480 - 60 = 420 menit

Availability = 420 / 480 = 0.875000  (87.5%)

Theoretical Max = 380 × 1.0 / 420 = 0.904762
Performance = 0.904762  (90.5%, tidak di-cap karena < 1.0)

Quality = 370 / 380 = 0.973684  (97.4%)

OEE = 0.875000 × 0.904762 × 0.973684
    = 0.771099  (77.1%)
    → Typical range, ada ruang perbaikan di Availability
```

---

## Pareto Analysis Downtime

Mengidentifikasi "vital few" — 20% penyebab yang menyumbang 80% downtime.

### Algoritma

```
INPUT:
  date_from, date_to    — rentang tanggal
  work_center_id        — optional filter (null = semua mesin)

PROSES:
  1. JOIN downtime_events → production_logs
     WHERE log_date BETWEEN date_from AND date_to
     AND (work_center_id = ? OR ? IS NULL)

  2. GROUP BY reason_category
     SELECT SUM(duration_minutes) AS total_minutes

  3. Sort DESC by total_minutes

  4. Hitung total keseluruhan = Σ total_minutes

  5. Untuk setiap kategori:
     percentage   = total_minutes / total_keseluruhan × 100
     cumulative   = cumulative sebelumnya + percentage

OUTPUT (array):
  [
    { category: 'breakdown', total_minutes: 480, percentage: 45.2, cumulative: 45.2 },
    { category: 'setup',     total_minutes: 320, percentage: 30.1, cumulative: 75.3 },
    { category: 'material',  total_minutes: 150, percentage: 14.1, cumulative: 89.4 },
    { category: 'operator',  total_minutes: 80,  percentage: 7.5,  cumulative: 96.9 },
    { category: 'other',     total_minutes: 33,  percentage: 3.1,  cumulative: 100.0 },
  ]
```

### Kategori Downtime (reason_category ENUM)

| Kategori | Deskripsi | Contoh |
|---|---|---|
| `breakdown` | Kerusakan mesin tak terduga | Motor terbakar, belt putus |
| `setup` | Pergantian produk / changeover | Ganti cetakan, setting ulang |
| `material` | Menunggu material / komponen | Material habis, salah kirim |
| `operator` | Tidak ada operator | Absen, meeting, istirahat panjang |
| `other` | Lainnya | Listrik padam, inspeksi |

---

## OEE Trend & Benchmark

### Trend Data (untuk sparkline chart)
```php
public function trendData(int $workCenterId, Carbon $from, Carbon $to): array
// Return: array of { date, availability, performance, quality, oee }
// Diambil dari oee_snapshots, digroup per tanggal (rata-rata semua shift)
```

### World Class Benchmark
```php
public function benchmarkVsWorldClass(OeeSnapshot $snapshot): array
// Return:
// {
//   oee:          { actual: 0.771, world_class: 0.85, gap: -0.079 },
//   availability: { actual: 0.875, world_class: 0.90, gap: -0.025 },
//   performance:  { actual: 0.905, world_class: 0.95, gap: -0.045 },
//   quality:      { actual: 0.974, world_class: 0.9999, gap: -0.026 },
// }
```

---

## Real-time Update Flow (Soketi)

```
Operator submit form log produksi
  → POST /production-logs
  → ProductionLogController@store
  → ProductionLog::create()
  → ProductionLogObserver@created
  → dispatch(new ProductionLogSaved($log))
  → RecalculateOeeJob (queued, database driver)
      → OeeCalculatorService::compute($log)
      → OeeSnapshot::updateOrCreate(...)
      → broadcast(new OeeUpdated($snapshot))
          → channel: work-center.{work_center_id}
          → event: oee.updated
  → Vue: Echo.private('work-center.X').listen('OeeUpdated', callback)
  → OeeGauge.vue reactive update tanpa reload halaman
```

### Konfigurasi Soketi (.env)
```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=factoryos
PUSHER_APP_KEY=factoryos-key
PUSHER_APP_SECRET=factoryos-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
```
