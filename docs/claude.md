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
- (belum ada — project baru)

### 🔄 In Progress
- Setup & scaffolding awal

### ⏳ Not Started
- Semua fitur (lihat Roadmap)

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

---

## Main Services

| Service                   | Tanggung Jawab                                     | Status   |
| ------------------------- | -------------------------------------------------- | -------- |
| `JobShopSchedulerService` | Jalankan 4 dispatching rules, simpan schedule      | ⏳ Belum |
| `GanttBuilderService`     | Transform assignments → D3.js-ready dataset        | ⏳ Belum |
| `OeeCalculatorService`    | Hitung OEE, Pareto, trend data, benchmark          | ⏳ Belum |
| `EoqCalculatorService`    | EOQ, Safety Stock, ROP, Total Annual Cost (bcmath) | ⏳ Belum |
| `MrpService`              | MRP explosion: schedule → material requirements    | ⏳ Belum |
| `ExportService`           | Orkestrasi PDF & Excel export per engine           | ⏳ Belum |

---

## Formulas Quick Reference

```
# ENGINE 1 — JOB SHOP SCHEDULING
SPT score   = processing_time (ascending)
EDD score   = due_date (ascending)
CR score    = (due_date - now).minutes / remaining_processing_time (ascending)
FIFO score  = work_order.created_at (ascending)
Makespan    = max(completion_time) semua operations
Tardiness_i = max(0, last_op_end_i - due_date_i)
Total Tard. = Σ Tardiness_i
Mean Flow   = Σ(last_op_end_i - release_date_i) / n

# ENGINE 2 — OEE (ISO 22400)
Availability = (Planned - Downtime) / Planned
Performance  = (Output × IdleCycleTime) / OperatingTime  [cap 1.0]
Quality      = GoodOutput / TotalOutput
OEE          = Availability × Performance × Quality

# ENGINE 3 — INVENTORY
EOQ          = √(2 × D × S / H)
Safety Stock = Z × σ_d × √(LT)
ROP          = (avg_daily_demand × LT) + Safety Stock
Net Req(t)   = max(0, GrossReq(t) - ProjOnHand(t-1) - ScheduledReceipts(t))
```

---

## Roadmap per Phase

### Phase 1 — Foundation (Week 1–2)
- [ ] Laravel scaffolding + Breeze + Inertia + Vue 3
- [ ] Semua migrations sekaligus
- [ ] Models + relationships + factories
- [ ] Master data CRUD: WorkCenter, Product, Material
- [ ] BOM editor + Routing sequence editor
- [ ] WorkOrder CRUD + generate wo_operations dari routing

### Phase 2 — Engine 1: Scheduler (Week 3–4)
- [ ] SchedulingAlgorithmInterface + 4 implementasi (SPT, EDD, CR, FIFO)
- [ ] JobShopSchedulerService::run() dan compareAll()
- [ ] RunSchedulingJob (queued)
- [ ] GanttBuilderService → D3-ready JSON
- [ ] GanttChart.vue interaktif + Compare.vue

### Phase 3 — Engine 2: OEE (Week 5–6)
- [ ] OeeCalculatorService (bcmath)
- [ ] ProductionLogObserver + RecalculateOeeJob + Soketi broadcast
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
