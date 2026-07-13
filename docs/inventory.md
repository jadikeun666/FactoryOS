# docs/inventory.md — Engine 3: Inventory Optimizer (MRP-lite)

## Referensi

- Chopra, S. & Meindl, P. (2016). *Supply Chain Management* (6th ed.). Pearson.
  → Bab 11 (EOQ), Bab 12 (Safety Stock & Service Level)
- Vollmann, T.E. et al. (2005). *Manufacturing Planning and Control for Supply Chain Management*. McGraw-Hill.
  → Bab 3–5: MRP logic, BOM explosion, lot sizing
- Silver, E.A., Pyke, D.F., & Thomas, D.J. (2017). *Inventory and Production Management in Supply Chains* (4th ed.). CRC Press.
  → Derivasi EOQ, safety stock, probabilistic demand

---

## EOQ — Economic Order Quantity

### Tujuan
Minimasi Total Annual Inventory Cost = Ordering Cost + Holding Cost.

### Derivasi Formula
```
Total Annual Cost (TAC) = (D/Q) × S + (Q/2) × H

D = Annual Demand (unit/tahun)
Q = Order Quantity (unit/order) ← yang dicari
S = Ordering Cost per order (Rp/PO) — biaya administrasi, pengiriman
H = Holding Cost per unit per tahun (Rp/unit/tahun)
    = biasanya 20–30% × unit_cost

Minimasi TAC → d(TAC)/dQ = 0:
  -DS/Q² + H/2 = 0
  Q² = 2DS/H
  EOQ = √(2DS/H)   ← formula final
```

### Formula Biaya pada EOQ
```
Annual Ordering Cost = (D / EOQ) × S
Annual Holding Cost  = (EOQ / 2) × H
TAC pada EOQ         = Annual Ordering Cost + Annual Holding Cost
                     (keduanya sama besar di titik optimal — properti EOQ)
```

### Contoh Perhitungan
```
D = 1.200 unit/tahun
S = Rp 150.000/order
H = Rp 5.000/unit/tahun  (= 25% × unit_cost Rp 20.000)

EOQ = √(2 × 1200 × 150000 / 5000)
    = √(360.000.000 / 5.000)
    = √72.000
    = 268,33 → dibulatkan ke atas = 269 unit/order

Annual Ordering Cost = (1200/268.33) × 150.000 = Rp 671.000
Annual Holding Cost  = (268.33/2) × 5.000 = Rp 671.000  (sama — benar)
TAC = Rp 1.342.000/tahun
```

### Implementasi bcmath
```php
// app/Services/Inventory/EoqCalculatorService.php

public function computeEoq(InventoryParam $p): string
{
    $scale = 6;
    $numerator   = bcmul(bcmul('2', (string)$p->annual_demand, $scale), (string)$p->ordering_cost, $scale);
    $raw_eoq_sq  = bcdiv($numerator, (string)$p->holding_cost_per_unit_year, $scale);
    // bcmath tidak punya sqrt — gunakan Newton-Raphson
    return $this->bcSqrt($raw_eoq_sq, $scale);
}

private function bcSqrt(string $n, int $scale): string
{
    // Newton-Raphson: x_{n+1} = (x_n + n/x_n) / 2
    $x = $n;
    for ($i = 0; $i < 100; $i++) {
        $x_new = bcdiv(bcadd($x, bcdiv($n, $x, $scale + 2), $scale + 2), '2', $scale + 2);
        if (bccomp($x_new, $x, $scale) === 0) break;
        $x = $x_new;
    }
    return bcadd($x, '0', $scale); // round ke scale
}
```

---

## Safety Stock & Reorder Point

### Formula
```
Safety Stock = Z × σ_d × √(LT)

Z    = Z-score sesuai service level yang diinginkan
σ_d  = standar deviasi demand harian (unit/hari)
LT   = lead time supplier (hari)

Reorder Point (ROP) = (avg_daily_demand × LT) + Safety Stock
```

### Tabel Z-Score
| Service Level | Z-score |
|---|---|
| 90% | 1.282 |
| 95% | 1.645 ← default |
| 97.5% | 1.960 |
| 99% | 2.326 |
| 99.9% | 3.090 |

### Contoh Perhitungan
```
avg_daily_demand = 10 unit/hari
lead_time        = 7 hari
σ_d              = 3 unit/hari (standar deviasi dari historis)
Z                = 1.645 (95% service level)

Safety Stock = 1.645 × 3 × √7
             = 1.645 × 3 × 2.6458
             = 13.05 → dibulatkan ke atas = 14 unit

ROP = (10 × 7) + 14 = 84 unit

Interpretasi:
  Ketika qty_on_hand ≤ 84 unit → buat Purchase Order sebesar EOQ
  Safety stock 14 unit melindungi dari variabilitas demand selama lead time
```

### Implementasi bcmath
```php
public function computeSafetyStock(InventoryParam $p): string
{
    $scale = 6;
    $sqrt_lt = $this->bcSqrt((string)$p->lead_time_days, $scale);
    return bcmul(bcmul((string)$p->service_level_z, (string)$p->demand_std_dev, $scale), $sqrt_lt, $scale);
}

public function computeRop(InventoryParam $p): string
{
    $scale = 6;
    $avg_daily = bcdiv((string)$p->annual_demand, '365', $scale);
    $cycle_stock = bcmul($avg_daily, (string)$p->lead_time_days, $scale);
    return bcadd($cycle_stock, $this->computeSafetyStock($p), $scale);
}
```

---

## MRP — Material Requirements Planning

### Konsep
MRP menghitung kapan dan berapa banyak material perlu dipesan berdasarkan:
- Jadwal produksi (dari Engine 1)
- BOM (Bill of Materials)
- Stok on-hand saat ini
- Scheduled receipts (PO yang sudah dibuat)
- Lead time supplier per material

### Logic Period-by-Period (Backward Scheduling)

```
Untuk setiap material, hitung per periode (hari):

Gross_Requirement(t)    = Σ (qty_WO × qty_per_unit dari BOM)
                          untuk semua WO yang operasi terakhir jatuh pada periode t

Scheduled_Receipts(t)   = PO yang dijadwalkan datang pada periode t
                          (diambil dari inventory_transactions type='in' yang planned)

Projected_On_Hand(t)    = Projected_On_Hand(t-1)
                          + Scheduled_Receipts(t)
                          - Gross_Requirement(t)

Net_Requirement(t)      = max(0, Gross_Requirement(t)
                              - Projected_On_Hand(t-1)
                              - Scheduled_Receipts(t))

Planned_Order_Release   = jika Net_Requirement(t) > 0:
                            qty  = roundUpToEoq(Net_Requirement(t))
                            date = t - lead_time_days  ← order harus keluar hari ini
```

### Contoh MRP Grid

```
Material: Besi Plat 2mm  |  Lead Time: 3 hari  |  EOQ: 100 lembar
On-hand awal: 50 lembar

Periode (hari)              | t=1  | t=2  | t=3  | t=4  | t=5
Gross Requirement           |  0   |  30  |  0   |  60  |  20
Scheduled Receipts          | 100  |  0   |  0   |  0   |  0
Projected On-Hand (sebelum) |  50  | 150  | 120  | 120  |  60
Projected On-Hand (setelah) | 150  | 120  | 120  |  60  |  40
Net Requirement             |  0   |  0   |  0   |  0   |  0
Planned Order Release       |  -   |  -   |  -   |  -   |  -

→ Tidak ada planned order karena stok + SR cukup.

Jika on-hand awal = 10:
Projected On-Hand (t=2)    = 10 + 0 - 30 = -20
Net Requirement (t=2)      = max(0, -20) = 20 → roundUp ke EOQ = 100
Planned Order Release       = 100 lembar, dikeluarkan pada t=2-3 = t= -1 (HARI INI atau KEMARIN)
→ Trigger REORDER ALERT: material sudah terlambat, perlu expediting
```

### MRP Service Structure
```php
// app/Services/Inventory/MrpService.php

public function run(int $scheduleId): MrpRun;
// Buat MrpRun baru, explosion BOM untuk setiap WO dalam schedule

public function explodeBom(WorkOrder $wo, Carbon $dueDate): array;
// Return: [ material_id => [ date => gross_requirement ] ]

public function computeRequirements(Material $material, array $grossReqs, Carbon $from): array;
// Return: array MRP row per periode

public function checkReorderAlerts(): Collection;
// Bandingkan qty_on_hand dengan ROP per material, buat/update reorder_alerts
```

---

## Reorder Alert Logic

```
Setiap hari (via CheckReorderAlertsJob, scheduled 06:00):

for each material:
  current_qty = inventory.qty_on_hand + inventory.qty_on_order
  rop         = inventory_params.rop

  if current_qty <= rop:
    if no open alert for this material:
      ReorderAlert::create({
        material_id:  material.id,
        current_qty:  current_qty,
        rop_qty:      rop,
        eoq_qty:      inventory_params.eoq,
        status:       'open'
      })
      fire ReorderAlertRaised event → NotifyPpicListener

Alert status transitions:
  open → acknowledged  (PPIC sudah lihat)
  acknowledged → ordered (PO sudah dibuat)
  ordered → (alert ditutup otomatis saat inventory_transaction type='in' masuk)
```
