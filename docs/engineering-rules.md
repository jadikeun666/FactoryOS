# docs/engineering-rules.md — Engineering Rules & Testing Policy

## 1. Presisi Numerik — bcmath WAJIB

**Aturan:** Semua kalkulasi kritis (OEE, EOQ, Safety Stock, MRP, scheduling metrics)
WAJIB menggunakan PHP `bcmath`. Jangan pernah pakai PHP `float` atau `double`
untuk angka yang akan dipakai dalam keputusan produksi.

**Alasan:**
```
PHP float: 0.1 + 0.2 = 0.30000000000000004  (floating point error)
bcmath:    bcadd('0.1', '0.2', 10) = '0.3000000000'  (tepat)
```
Pada ribuan transaksi inventory atau ratusan iterasi MRP, error akumulatif dari
float bisa menyebabkan Net Requirement yang salah → keputusan pembelian material keliru.

**Aturan teknis:**
- Selalu cast ke `string` sebelum operasi bcmath: `(string) $model->qty`
- Scale: gunakan 6 untuk rasio (OEE), 4 untuk qty dan biaya
- Simpan ke DB sebagai `NUMERIC(15,4)` atau `NUMERIC(8,6)` — jangan `FLOAT`
- `bcSqrt` tidak ada di PHP — implementasi Newton-Raphson sendiri (lihat `docs/inventory.md`)

```php
// Benar
$oee = bcmul(bcmul($availability, $performance, 6), $quality, 6);

// Salah
$oee = $availability * $performance * $quality;
```

---

## 2. Immutability Rules

### Schedule — Immutable
Setiap run algoritma scheduler membuat record `Schedule` baru. Record lama
tidak pernah di-update atau dihapus. Ini memungkinkan:
- Audit trail: siapa menjalankan algoritma apa kapan
- Perbandingan historis antar run
- Rollback ke jadwal sebelumnya jika diperlukan

```php
// Benar: buat record baru
$schedule = Schedule::create([...]);

// Salah: update schedule existing
$schedule->update([...]); // ← DILARANG
```

### Production Log — Immutable Setelah Validated
Log produksi yang sudah di-validate (`is_validated = true`) tidak bisa diedit.
Koreksi dilakukan dengan membuat adjustment entry baru (inventory_transaction type='adjust').

Enforce di Policy:
```php
// app/Policies/ProductionLogPolicy.php
public function update(User $user, ProductionLog $log): bool {
    if ($log->is_validated) return false;
    return $user->id === $log->created_by || $user->isAdmin();
}
```

### Inventory Transaction — Immutable
Setiap pergerakan stok adalah record baru di `inventory_transactions`. Tidak ada
update atau delete. Koreksi = entry baru dengan type='adjust' dan qty negatif/positif.

---

## 3. Database Rules

- `NUMERIC(15,4)` untuk semua qty, biaya, ukuran
- `NUMERIC(8,6)` untuk semua rasio 0–1 (OEE components)
- `NUMERIC(10,6)` untuk cycle time (bisa desimal kecil)
- JANGAN `FLOAT`, `DOUBLE`, `REAL` di PostgreSQL untuk kolom kalkulasi
- Setiap tabel punya `created_at`, `updated_at` (kecuali yang immutable: hanya `created_at`)
- Foreign key selalu `ON DELETE RESTRICT` untuk data master (material, product)
- Foreign key `ON DELETE CASCADE` hanya untuk detail yang tidak bermakna tanpa parent-nya

---

## 4. Architecture Rules

**Thin Controllers:**
Controller hanya boleh:
1. Menerima request (sudah divalidasi Form Request)
2. Memanggil satu atau dua Service method
3. Mengembalikan Inertia response atau JSON

Controller TIDAK BOLEH:
- Mengandung formula atau kalkulasi apapun
- Query Eloquent langsung (kecuali sangat sederhana)
- Logic percabangan bisnis

**Service Layer:**
- Satu Service = satu domain tanggung jawab
- Service boleh memanggil Service lain via dependency injection
- Service TIDAK BOLEH memanggil Controller

**Constructor Injection:**
```php
// Benar
class MrpController {
    public function __construct(
        private readonly MrpService $mrp,
        private readonly ExportService $export,
    ) {}
}

// Salah — resolve manual
$mrp = new MrpService();               // ← DILARANG
$mrp = app(MrpService::class);         // ← Hanya boleh di ServiceProvider/test
```

---

## 5. Bahasa

- **Code** (class, method, variable, kolom DB): **English**
- **UI** (label, placeholder, pesan error, tooltip): **Bahasa Indonesia**
- **Komentar code**: boleh Bahasa Indonesia untuk penjelasan engineering

```php
// Benar
public function computeSafetyStock(InventoryParam $params): string
{
    // Safety stock melindungi dari variabilitas demand selama lead time
    $sqrt_lt = $this->bcSqrt((string)$params->lead_time_days, 6);
    ...
}
```

---

## 6. Testing Policy

### Unit Tests — Wajib untuk Setiap Service

Setiap Service Method yang mengandung kalkulasi WAJIB punya unit test
dengan data yang diverifikasi manual (gunakan referensi dari docs/).

```php
// tests/Unit/Services/EoqCalculatorServiceTest.php
class EoqCalculatorServiceTest extends TestCase {
    private EoqCalculatorService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new EoqCalculatorService();
    }

    /** @test */
    public function it_computes_eoq_correctly(): void
    {
        // Data dari docs/inventory.md contoh perhitungan
        $params = InventoryParam::factory()->make([
            'annual_demand'              => 1200,
            'ordering_cost'              => 150000,
            'holding_cost_per_unit_year' => 5000,
        ]);

        $eoq = $this->service->computeEoq($params);

        // √(2 × 1200 × 150000 / 5000) = √72000 ≈ 268.328
        $this->assertEquals('268.3281', bcadd($eoq, '0', 4));
    }

    /** @test */
    public function it_computes_oee_correctly(): void
    {
        // Data dari docs/oee-formulas.md contoh perhitungan
        // Availability = 0.875, Performance = 0.904762, Quality = 0.973684
        // OEE = 0.770833
        ...
    }
}
```

### Feature Tests — Wajib untuk Setiap Controller

```php
// tests/Feature/Controllers/ScheduleControllerTest.php
class ScheduleControllerTest extends TestCase {
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_run_spt_schedule(): void
    {
        $user = User::factory()->create();
        WorkOrder::factory()->count(3)->withOperations()->create();

        $response = $this->actingAs($user)
            ->post(route('schedules.run'), ['algorithm' => 'spt']);

        $response->assertRedirect();
        $this->assertDatabaseHas('schedules', ['algorithm' => 'spt']);
        $this->assertDatabaseCount('schedule_assignments', 9); // 3 WO × 3 ops
    }
}
```

### Test Sebelum Commit
Jalankan `php artisan test` sebelum setiap sesi selesai. Target: **zero failures**.

---

## 7. Performance Cap — OEE Performance

OEE Performance WAJIB di-cap pada 1.0 (100%). Nilai > 1.0 tidak valid secara fisik
(mesin tidak bisa berproduksi melebihi kapasitas teoritis-nya secara definitif).

Jika data menghasilkan Performance > 1.0, ini mengindikasikan:
- `ideal_cycle_time_minutes` terlalu tinggi (perlu dikalibrasi)
- Data `actual_output` ada kesalahan input

Cap di layer Service, bukan di DB atau View:
```php
$performance = bccomp($raw, '1.000000', 6) > 0 ? '1.000000' : $raw;
```

---

## 8. EOQ Assumptions & Limitations

EOQ klasik memiliki asumsi yang harus dipahami tim:

| Asumsi EOQ | Implikasi di FactoryOS |
|---|---|
| Demand deterministik & konstan | Pakai `annual_demand` rata-rata historis |
| Lead time konstan | Pakai `lead_time_days` tetap per supplier |
| Tidak ada quantity discount | Jika supplier beri diskon volume, EOQ tidak optimal |
| Tidak ada stockout cost eksplisit | Safety stock mengatasi ini secara implisit |
| Satu item dikerjakan sendiri | EOQ per material dihitung independen |

Untuk kasus dengan demand sangat fluktuatif: naikkan `demand_std_dev` dan
`service_level_z` untuk mendapat safety stock yang lebih konservatif.

---

## 9. MRP Tidak Pertimbangkan Kapasitas Mesin

MRP Engine 3 menghitung kebutuhan material berdasarkan jadwal dari Engine 1,
tapi tidak memvalidasi ulang apakah kapasitas mesin cukup.

Kapasitas sudah dihandle di Engine 1 (scheduling). Kedua engine harus dijalankan
secara berurutan: **run Schedule dulu → baru run MRP** dari schedule tersebut.
Jika schedule berubah (run algoritma baru), MRP harus di-run ulang.

---

## 10. Tidak Ada Paid AI API

Semua intelligence di FactoryOS adalah algoritma deterministik:
- Scheduling: dispatching rules (SPT, EDD, CR, FIFO)
- Inventory: EOQ, Safety Stock, ROP (formula closed-form)
- OEE: formula ISO 22400
- MRP: period-by-period netting

Tidak ada dependency ke OpenAI, Anthropic, atau API berbayar lainnya.
Jika di masa depan ingin tambah AI: gunakan Ollama (local, gratis) secara opsional.
