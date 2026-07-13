# docs/scheduling.md — Engine 1: Job Shop Scheduler

## Referensi Akademik

- Pinedo, M.L. (2016). *Scheduling: Theory, Algorithms, and Systems* (5th ed.). Springer.
  → Bab 3 (Single Machine), Bab 6 (Job Shops) — sumber utama dispatching rules
- Baker, K.R. & Trietsch, D. (2009). *Principles of Sequencing and Scheduling*. Wiley.
  → Derivasi formula Tardiness, Flow Time, Critical Ratio
- ISO 22400-2:2014 — Key Performance Indicators for Manufacturing

---

## Konsep Dasar

Job Shop Scheduling Problem (JSSP): sekumpulan Work Order (jobs), masing-masing
punya urutan operasi yang harus dikerjakan di mesin tertentu (routing).

Tujuan: urutan pengerjaan yang meminimalkan makespan dan tardiness.

JSSP adalah problem NP-hard. Solusi praktis menggunakan dispatching rules (heuristik).
Untuk skala pabrik menengah (≤ 50 WO, ≤ 20 mesin), dispatching rules sudah cukup efektif.

---

## Dispatching Rules

### SPT — Shortest Processing Time
```
Score = std_process_time_minutes + setup_time_minutes  (ascending)
```
Pilih operasi dengan total waktu proses terpendek di mesin yang idle.
- Bagus untuk: minimize average flow time, minimize WIP (Work In Process)
- Lemah: WO dengan waktu panjang bisa terus tertunda (starvation)

### EDD — Earliest Due Date
```
Score = work_order.due_date  (ascending)
```
Pilih operasi dari WO dengan due date paling awal.
- Bagus untuk: minimize maximum lateness
- Lemah: tidak mempertimbangkan sisa waktu proses; WO kecil bisa terlambat

### CR — Critical Ratio
```
Score = (due_date - now).inMinutes / total_remaining_processing_time  (ascending)

CR < 1.0 → WO sudah kritis / akan terlambat
CR = 1.0 → tepat waktu jika dikerjakan sekarang
CR > 1.0 → masih ada slack
```
Pilih operasi dengan CR terkecil (paling kritis).
- Bagus untuk: balance antara due date dan workload tersisa, paling adaptif
- Lemah: perlu hitung ulang setiap kali ada operasi selesai

### FIFO — First In First Out
```
Score = work_order.created_at  (ascending)
```
Urutan berdasarkan waktu WO dibuat. Tidak ada optimasi — dipakai sebagai baseline.

---

## Algoritma Scheduling — Pseudocode Lengkap

```
INPUT:
  work_orders[]       — WO dengan status draft/scheduled
  wo_operations[]     — operasi per WO, sudah urut per sequence
  work_centers[]      — mesin dengan kapasitas
  start_time          — Carbon (biasanya today 07:00)
  algorithm           — 'spt' | 'edd' | 'cr' | 'fifo'

OUTPUT:
  schedule            — record Schedule dengan metrics
  schedule_assignments[] — satu baris per wo_operation

PROSES:

1. INISIALISASI
   machine_available_at = {}
   for each work_center:
     machine_available_at[work_center.id] = start_time

   job_ready_at = {}
   for each work_order:
     job_ready_at[work_order.id] = max(start_time, work_order.release_date)

   scheduled_ops = {}    // op_id → { start, end }
   assignments   = []

2. LOOP sampai semua wo_operations terjadwal:

   a. KUMPULKAN CANDIDATES:
      candidates = []
      for each wo_operation op:
        if op already scheduled: skip
        if op.sequence == 1: eligible = true
        else:
          prev_op = wo_operation dengan sequence = op.sequence - 1, WO sama
          eligible = prev_op.id ada di scheduled_ops
        if eligible: candidates.append(op)

   b. Jika candidates kosong tapi masih ada operasi pending:
      → ada circular dependency atau data error, throw SchedulingException

   c. RANK candidates menggunakan dispatching rule (lihat formula di atas)
      Untuk CR: hitung total_remaining = sum(process + setup) semua ops belum selesai di WO

   d. Ambil candidate teratas (operasi X di mesin M):
      earliest_start = max(
        machine_available_at[M],
        job_ready_at[work_order_of_X]
      )
      planned_start = earliest_start
      planned_end   = planned_start + op.std_process_time_minutes + op.setup_time_minutes

      simpan ke scheduled_ops[op.id] = { planned_start, planned_end }
      simpan ke assignments[]
      machine_available_at[M] = planned_end
      job_ready_at[work_order_of_X] = planned_end

   e. Ulangi dari langkah (a)

3. HITUNG METRICS:
   makespan = max(planned_end) di seluruh assignments

   for each work_order:
     last_end = max(planned_end) dari operations WO ini
     tardiness = max(0, last_end - due_date)  // dalam menit
     flow_time = last_end - release_date

   total_tardiness      = Σ tardiness per WO
   late_wo_count        = count(tardiness > 0)
   mean_flow_time       = Σ flow_time / count(work_orders)

4. SIMPAN:
   Schedule::create({ algorithm, makespan, total_tardiness, late_wo_count, mean_flow_time, ... })
   ScheduleAssignment::insert(assignments[])  // bulk insert
```

---

## Contoh Walkthrough: 2 Mesin, 3 WO

```
Work Centers: M1, M2
Work Orders:
  WO-A: due 2 hari, routing → [Op1: M1 (60 min), Op2: M2 (40 min)]
  WO-B: due 1 hari, routing → [Op1: M2 (30 min), Op2: M1 (90 min)]
  WO-C: due 3 hari, routing → [Op1: M1 (45 min)]

Start time: 07:00

--- SPT Run ---
Candidates awal: WO-A/Op1 (M1,60), WO-B/Op1 (M2,30), WO-C/Op1 (M1,45)

Iterasi 1: SPT → pilih WO-B/Op1 (30 min, terpendek)
  M2: 07:00 → 07:30, job_ready[WO-B] = 07:30

Iterasi 2: Candidates: WO-A/Op1(M1,60), WO-C/Op1(M1,45), WO-B/Op2(M1,90)
  SPT → pilih WO-C/Op1 (45 min)
  M1: 07:00 → 07:45, job_ready[WO-C] = 07:45

Iterasi 3: Candidates: WO-A/Op1(M1,60), WO-B/Op2(M1,90)
  SPT → pilih WO-A/Op1 (60 min)
  M1: 07:45 → 08:45, job_ready[WO-A] = 08:45

...dan seterusnya

--- EDD Run ---
Iterasi 1: EDD → WO-B due paling awal (1 hari) → pilih WO-B/Op1
  (meski bukan di M1, EDD tetap prioritaskan WO dengan due date terdekat)
```

---

## Edge Cases

| Situasi | Penanganan |
|---|---|
| WO release_date di masa depan | job_ready_at = release_date, mesin menunggu |
| Dua operasi di mesin sama, WO berbeda | Antrian: pilih berdasarkan dispatching rule |
| Operasi tanpa setup time | setup_time_minutes = 0, tidak error |
| WO tanpa routing | Validasi di WorkOrderController sebelum scheduling |
| Semua mesin sibuk, tidak ada kandidat | Loop tunggu mesin tercepat selesai (lihat step 2b) |
| CR denominator = 0 (semua op selesai) | CR = 0, operasi ini prioritas tertinggi |

---

## Service Structure

```php
// app/Services/Scheduling/Contracts/SchedulingAlgorithmInterface.php
interface SchedulingAlgorithmInterface {
    public function score(WoOperation $op, Carbon $now, array $remainingByWo): string;
    // return string untuk kompatibilitas bcmath sorting
}

// app/Services/Scheduling/Algorithms/SptAlgorithm.php
// app/Services/Scheduling/Algorithms/EddAlgorithm.php
// app/Services/Scheduling/Algorithms/CrAlgorithm.php
// app/Services/Scheduling/Algorithms/FifoAlgorithm.php

// app/Services/Scheduling/JobShopSchedulerService.php
class JobShopSchedulerService {
    public function run(string $algorithm, Carbon $startFrom): Schedule;
    public function compareAll(Carbon $startFrom): array;     // 4 algoritma sekaligus
    public function computeMetrics(array $assignments, Collection $workOrders): array;
    private function buildCandidates(Collection $pending, array $scheduledIds): Collection;
    private function resolveAlgorithm(string $name): SchedulingAlgorithmInterface;
}

// app/Services/Scheduling/GanttBuilderService.php
class GanttBuilderService {
    public function build(Schedule $schedule): array;  // → JSON untuk D3.js
}
```

---

## Metrics Benchmark

| Metric | Keterangan | Satuan |
|---|---|---|
| Makespan | Waktu total dari start hingga semua WO selesai | Menit |
| Total Tardiness | Jumlah keterlambatan semua WO | Menit |
| Late WO Count | Jumlah WO yang terlambat | WO |
| Mean Flow Time | Rata-rata waktu WO dari release hingga selesai | Menit |

Tampilkan perbandingan 4 algoritma dalam satu tabel di `Scheduling/Compare.vue`.
User memilih algoritma terbaik, lalu "apply" schedule tersebut ke wo_operations.
