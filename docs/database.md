# docs/database.md — Database Schema & Conventions

## Conventions

- Semua angka kalkulasi: `NUMERIC(15,4)` untuk qty, `NUMERIC(8,6)` untuk rasio (0–1)
- Jangan pernah gunakan `FLOAT` atau `DOUBLE` di PostgreSQL untuk angka kritis
- Semua tabel pakai `id` bigserial primary key
- Semua tabel pakai `created_at`, `updated_at` (Laravel timestamps)
- Soft delete hanya pada tabel yang data historisnya penting (lihat keterangan per tabel)
- Foreign key selalu diberi nama eksplisit untuk kemudahan debugging

---

## Master Data

### work_centers
```sql
id                        BIGSERIAL PRIMARY KEY
name                      VARCHAR(100) NOT NULL
code                      VARCHAR(20) NOT NULL UNIQUE   -- kode mesin: M01, CNC-02
capacity_per_shift_minutes NUMERIC(10,2) NOT NULL DEFAULT 480
setup_time_minutes        NUMERIC(10,2) NOT NULL DEFAULT 0
is_active                 BOOLEAN NOT NULL DEFAULT true
description               TEXT
created_at, updated_at    TIMESTAMP
```

### products
```sql
id                 BIGSERIAL PRIMARY KEY
name               VARCHAR(150) NOT NULL
sku                VARCHAR(50) NOT NULL UNIQUE
unit               VARCHAR(20) NOT NULL DEFAULT 'pcs'
description        TEXT
created_at, updated_at
```

### materials
```sql
id                 BIGSERIAL PRIMARY KEY
name               VARCHAR(150) NOT NULL
sku                VARCHAR(50) NOT NULL UNIQUE
unit               VARCHAR(20) NOT NULL DEFAULT 'pcs'
unit_cost          NUMERIC(15,4) NOT NULL DEFAULT 0
description        TEXT
created_at, updated_at
```

### bill_of_materials
```sql
id                 BIGSERIAL PRIMARY KEY
product_id         BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE
material_id        BIGINT NOT NULL REFERENCES materials(id) ON DELETE RESTRICT
qty_per_unit       NUMERIC(15,6) NOT NULL    -- jumlah material per 1 unit produk
unit               VARCHAR(20) NOT NULL
notes              TEXT
created_at, updated_at
UNIQUE(product_id, material_id)
```

### routings
```sql
id                          BIGSERIAL PRIMARY KEY
product_id                  BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE
sequence                    INT NOT NULL               -- urutan operasi (1, 2, 3, ...)
work_center_id              BIGINT NOT NULL REFERENCES work_centers(id) ON DELETE RESTRICT
std_process_time_minutes    NUMERIC(10,4) NOT NULL
setup_time_minutes          NUMERIC(10,4) NOT NULL DEFAULT 0
notes                       TEXT
created_at, updated_at
UNIQUE(product_id, sequence)
```

---

## Engine 1 — Job Shop Scheduler

### work_orders
```sql
id             BIGSERIAL PRIMARY KEY
product_id     BIGINT NOT NULL REFERENCES products(id) ON DELETE RESTRICT
qty            NUMERIC(15,4) NOT NULL
due_date       DATE NOT NULL
priority       INT NOT NULL DEFAULT 5          -- 1 = tertinggi, 10 = terendah
release_date   DATE NOT NULL DEFAULT CURRENT_DATE
status         VARCHAR(20) NOT NULL DEFAULT 'draft'
               -- ENUM: draft, scheduled, in_progress, done, late
notes          TEXT
created_by     BIGINT REFERENCES users(id)
created_at, updated_at
```

### wo_operations
```sql
id               BIGSERIAL PRIMARY KEY
work_order_id    BIGINT NOT NULL REFERENCES work_orders(id) ON DELETE CASCADE
routing_id       BIGINT NOT NULL REFERENCES routings(id) ON DELETE RESTRICT
work_center_id   BIGINT NOT NULL REFERENCES work_centers(id) ON DELETE RESTRICT
sequence         INT NOT NULL
planned_start    TIMESTAMP
planned_end      TIMESTAMP
actual_start     TIMESTAMP
actual_end       TIMESTAMP
status           VARCHAR(20) NOT NULL DEFAULT 'pending'
                 -- ENUM: pending, running, done, skipped
created_at, updated_at
```

### schedules
```sql
id                       BIGSERIAL PRIMARY KEY
algorithm                VARCHAR(10) NOT NULL   -- spt, edd, cr, fifo
makespan_minutes         NUMERIC(12,2)
total_tardiness_minutes  NUMERIC(12,2)
late_wo_count            INT
mean_flow_time_minutes   NUMERIC(12,2)
scheduled_from           TIMESTAMP NOT NULL
created_by               BIGINT REFERENCES users(id)
created_at               TIMESTAMP              -- tidak ada updated_at (immutable)
```
> Tidak ada soft delete, tidak ada update. Setiap run algoritma = record baru.

### schedule_assignments
```sql
id               BIGSERIAL PRIMARY KEY
schedule_id      BIGINT NOT NULL REFERENCES schedules(id) ON DELETE CASCADE
wo_operation_id  BIGINT NOT NULL REFERENCES wo_operations(id) ON DELETE CASCADE
work_center_id   BIGINT NOT NULL REFERENCES work_centers(id) ON DELETE RESTRICT
start_at         TIMESTAMP NOT NULL
end_at           TIMESTAMP NOT NULL
slot_index       INT NOT NULL       -- urutan slot di mesin ini dalam schedule ini
created_at       TIMESTAMP
```

---

## Engine 2 — OEE & Downtime

### shifts
```sql
id               BIGSERIAL PRIMARY KEY
name             VARCHAR(50) NOT NULL     -- Shift Pagi, Shift Siang, Shift Malam
start_time       TIME NOT NULL
end_time         TIME NOT NULL
planned_minutes  INT NOT NULL             -- durasi shift dalam menit
is_active        BOOLEAN NOT NULL DEFAULT true
created_at, updated_at
```

### production_logs
```sql
id                       BIGSERIAL PRIMARY KEY
work_center_id           BIGINT NOT NULL REFERENCES work_centers(id)
shift_id                 BIGINT NOT NULL REFERENCES shifts(id)
log_date                 DATE NOT NULL
planned_minutes          NUMERIC(10,2) NOT NULL
downtime_minutes         NUMERIC(10,2) NOT NULL DEFAULT 0
actual_output            NUMERIC(15,4) NOT NULL
good_output              NUMERIC(15,4) NOT NULL
ideal_cycle_time_minutes NUMERIC(10,6) NOT NULL   -- menit per unit pada kecepatan ideal
is_validated             BOOLEAN NOT NULL DEFAULT false
validated_at             TIMESTAMP
created_by               BIGINT REFERENCES users(id)
created_at, updated_at
UNIQUE(work_center_id, shift_id, log_date)
```
> Setelah `is_validated = true`, log tidak bisa diedit. Enforced di policy.

### downtime_events
```sql
id                  BIGSERIAL PRIMARY KEY
production_log_id   BIGINT NOT NULL REFERENCES production_logs(id) ON DELETE CASCADE
reason_category     VARCHAR(20) NOT NULL
                    -- ENUM: breakdown, setup, material, operator, other
reason_detail       VARCHAR(255)
duration_minutes    NUMERIC(10,2) NOT NULL
started_at          TIMESTAMP NOT NULL
created_at, updated_at
```

### oee_snapshots
```sql
id               BIGSERIAL PRIMARY KEY
work_center_id   BIGINT NOT NULL REFERENCES work_centers(id)
log_date         DATE NOT NULL
shift_id         BIGINT NOT NULL REFERENCES shifts(id)
availability     NUMERIC(8,6) NOT NULL
performance      NUMERIC(8,6) NOT NULL
quality          NUMERIC(8,6) NOT NULL
oee              NUMERIC(8,6) NOT NULL
computed_at      TIMESTAMP NOT NULL
UNIQUE(work_center_id, log_date, shift_id)
```

---

## Engine 3 — Inventory Optimizer

### inventory
```sql
id              BIGSERIAL PRIMARY KEY
material_id     BIGINT NOT NULL REFERENCES materials(id) UNIQUE
qty_on_hand     NUMERIC(15,4) NOT NULL DEFAULT 0
qty_on_order    NUMERIC(15,4) NOT NULL DEFAULT 0
last_updated    TIMESTAMP NOT NULL DEFAULT NOW()
```

### inventory_transactions
```sql
id               BIGSERIAL PRIMARY KEY
material_id      BIGINT NOT NULL REFERENCES materials(id)
type             VARCHAR(10) NOT NULL    -- ENUM: in, out, adjust
qty              NUMERIC(15,4) NOT NULL
unit_cost        NUMERIC(15,4)
reference_type   VARCHAR(50)            -- 'work_order', 'purchase_order', 'adjustment'
reference_id     BIGINT
notes            TEXT
created_by       BIGINT REFERENCES users(id)
created_at       TIMESTAMP              -- immutable, tidak ada updated_at
```

### inventory_params
```sql
id                          BIGSERIAL PRIMARY KEY
material_id                 BIGINT NOT NULL REFERENCES materials(id) UNIQUE
annual_demand               NUMERIC(15,4) NOT NULL
ordering_cost               NUMERIC(15,4) NOT NULL
holding_cost_per_unit_year  NUMERIC(15,4) NOT NULL
lead_time_days              INT NOT NULL
demand_std_dev              NUMERIC(10,4) NOT NULL DEFAULT 0
service_level_z             NUMERIC(6,4) NOT NULL DEFAULT 1.6450   -- 95%
eoq                         NUMERIC(15,4)          -- hasil kalkulasi
safety_stock                NUMERIC(15,4)          -- hasil kalkulasi
rop                         NUMERIC(15,4)          -- hasil kalkulasi
last_computed_at            TIMESTAMP
created_at, updated_at
```

### mrp_runs
```sql
id           BIGSERIAL PRIMARY KEY
schedule_id  BIGINT NOT NULL REFERENCES schedules(id)
computed_at  TIMESTAMP NOT NULL DEFAULT NOW()
created_by   BIGINT REFERENCES users(id)
created_at   TIMESTAMP
```

### mrp_requirements
```sql
id                    BIGSERIAL PRIMARY KEY
mrp_run_id            BIGINT NOT NULL REFERENCES mrp_runs(id) ON DELETE CASCADE
material_id           BIGINT NOT NULL REFERENCES materials(id)
period_date           DATE NOT NULL
gross_requirement     NUMERIC(15,4) NOT NULL DEFAULT 0
scheduled_receipts    NUMERIC(15,4) NOT NULL DEFAULT 0
projected_on_hand     NUMERIC(15,4) NOT NULL DEFAULT 0
net_requirement       NUMERIC(15,4) NOT NULL DEFAULT 0
planned_order_release NUMERIC(15,4) NOT NULL DEFAULT 0
created_at            TIMESTAMP
```

### reorder_alerts
```sql
id            BIGSERIAL PRIMARY KEY
material_id   BIGINT NOT NULL REFERENCES materials(id)
current_qty   NUMERIC(15,4) NOT NULL
rop_qty       NUMERIC(15,4) NOT NULL
eoq_qty       NUMERIC(15,4) NOT NULL
status        VARCHAR(20) NOT NULL DEFAULT 'open'
              -- ENUM: open, acknowledged, ordered
created_at, updated_at
```

---

## Indexes yang Direkomendasikan

```sql
-- Scheduling
CREATE INDEX idx_wo_ops_work_order ON wo_operations(work_order_id);
CREATE INDEX idx_wo_ops_work_center ON wo_operations(work_center_id);
CREATE INDEX idx_schedule_assignments_schedule ON schedule_assignments(schedule_id);

-- OEE
CREATE INDEX idx_prod_logs_date ON production_logs(log_date);
CREATE INDEX idx_prod_logs_wc_date ON production_logs(work_center_id, log_date);
CREATE INDEX idx_oee_wc_date ON oee_snapshots(work_center_id, log_date);
CREATE INDEX idx_downtime_prod_log ON downtime_events(production_log_id);

-- Inventory
CREATE INDEX idx_inv_txn_material ON inventory_transactions(material_id);
CREATE INDEX idx_inv_txn_created ON inventory_transactions(created_at);
CREATE INDEX idx_mrp_req_run ON mrp_requirements(mrp_run_id);
CREATE INDEX idx_mrp_req_material ON mrp_requirements(material_id, period_date);
CREATE INDEX idx_alerts_status ON reorder_alerts(status);
```
