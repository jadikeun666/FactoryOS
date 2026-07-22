<?php

namespace Tests\Unit\Services\Inventory;

use App\Models\BillOfMaterial;
use App\Models\Inventory;
use App\Models\InventoryParam;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Product;
use App\Models\ReorderAlert;
use App\Models\WorkOrder;
use App\Services\Inventory\EoqCalculatorService;
use App\Services\Inventory\MrpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MrpServiceTest — validasi terhadap contoh manual di
 * docs/inventory.md § Contoh MRP Grid (Besi Plat 2mm, Lead Time 3 hari,
 * EOQ 100 lembar).
 *
 * Pakai RefreshDatabase (bukan in-memory seperti EoqCalculatorServiceTest)
 * karena computeRequirements()/explodeBom() bergantung pada relasi
 * Eloquent nyata (material->inventory, material->inventoryParam,
 * wo->product->billOfMaterials) -- lihat claude.md catatan soal
 * EoqCalculatorService::computeAndSave() yang juga masuk "feature test
 * territory" untuk alasan yang sama.
 */
class MrpServiceTest extends TestCase
{
    use RefreshDatabase;

    private MrpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MrpService(new EoqCalculatorService());
    }

    /**
     * Skenario 1 dari docs/inventory.md: on-hand awal 50 lembar,
     * scheduled receipt 100 lembar masuk di t1. Ekspektasi: TIDAK ADA
     * Net Requirement di periode manapun karena stok + SR selalu cukup.
     */
    public function test_it_computes_requirements_with_sufficient_stock(): void
    {
        $material = Material::factory()->create(['name' => 'Besi Plat 2mm']);

        Inventory::create([
            'material_id'   => $material->id,
            'qty_on_hand'   => 50,
            'qty_on_order'  => 0,
            'last_updated'  => now(),
        ]);

        InventoryParam::create([
            'material_id'                => $material->id,
            'annual_demand'               => 3650,
            'ordering_cost'               => 150000,
            'holding_cost_per_unit_year'  => 5000,
            'lead_time_days'              => 3,
            'demand_std_dev'              => 3,
            'service_level_z'             => 1.6450,
            'eoq'                         => 100, // dipatok manual sesuai docs, bukan hasil formula
        ]);

        $t1 = Carbon::parse('2026-08-01');

        // Scheduled receipt 100 lembar di t1 (inventory_transactions type='in').
        // forceFill() dipakai karena created_at SENGAJA tidak ada di
        // $fillable InventoryTransaction (immutable, lihat model) --
        // create() biasa akan diam-diam mengabaikan created_at dan jatuh
        // ke default useCurrent() migration (waktu test run, bukan $t1).
        $transaction = new InventoryTransaction();
        $transaction->forceFill([
            'material_id' => $material->id,
            'type'        => 'in',
            'qty'         => 100,
            'created_at'  => $t1->copy(),
        ]);
        $transaction->save();

        $grossReqs = [
            $t1->toDateString()                    => '0',
            $t1->copy()->addDay()->toDateString()  => '30',   // t2
            $t1->copy()->addDays(2)->toDateString() => '0',   // t3
            $t1->copy()->addDays(3)->toDateString() => '60',  // t4
            $t1->copy()->addDays(4)->toDateString() => '20',  // t5
        ];

        $rows = $this->service->computeRequirements($material->fresh(['inventory', 'inventoryParam']), $grossReqs, $t1);

        $this->assertCount(5, $rows);

        // t1: GR=0, SR=100, POH sebelum=50 -> POH sesudah=150, NR=0
        $this->assertSame('0.000000', $rows[0]['gross_requirement']);
        $this->assertSame('100.000000', $rows[0]['scheduled_receipts']);
        $this->assertSame('150.000000', $rows[0]['projected_on_hand']);
        $this->assertSame('0.000000', $rows[0]['net_requirement']);
        $this->assertSame('0.000000', $rows[0]['planned_order_release']);

        // t2: GR=30, POH sesudah=120, NR=0
        $this->assertSame('120.000000', $rows[1]['projected_on_hand']);
        $this->assertSame('0.000000', $rows[1]['net_requirement']);

        // t3: GR=0, POH tetap 120
        $this->assertSame('120.000000', $rows[2]['projected_on_hand']);

        // t4: GR=60, POH sesudah=60
        $this->assertSame('60.000000', $rows[3]['projected_on_hand']);
        $this->assertSame('0.000000', $rows[3]['net_requirement']);

        // t5: GR=20, POH sesudah=40
        $this->assertSame('40.000000', $rows[4]['projected_on_hand']);
        $this->assertSame('0.000000', $rows[4]['net_requirement']);

        // Tidak ada satupun planned order release di seluruh periode.
        foreach ($rows as $row) {
            $this->assertSame('0.000000', $row['planned_order_release']);
            $this->assertNull($row['release_date']);
        }
    }

    /**
     * Skenario 2 dari docs/inventory.md: on-hand awal 10 lembar, TANPA
     * scheduled receipt. Ekspektasi: Net Requirement di t2 = max(0, 30 -
     * 10 - 0) = 20, dibulatkan ke EOQ (100) via roundUpToEoq, dan
     * release_date = period_date - lead_time_days (3 hari) --
     * mengindikasikan order sudah terlambat / perlu expediting sesuai
     * docs/inventory.md.
     */
    public function test_it_flags_net_requirement_and_rounds_up_to_eoq_with_low_stock(): void
    {
        $material = Material::factory()->create(['name' => 'Besi Plat 2mm']);

        Inventory::create([
            'material_id'  => $material->id,
            'qty_on_hand'  => 10,
            'qty_on_order' => 0,
            'last_updated' => now(),
        ]);

        InventoryParam::create([
            'material_id'                => $material->id,
            'annual_demand'               => 3650,
            'ordering_cost'               => 150000,
            'holding_cost_per_unit_year'  => 5000,
            'lead_time_days'              => 3,
            'demand_std_dev'              => 3,
            'service_level_z'             => 1.6450,
            'eoq'                         => 100,
        ]);

        $t2 = Carbon::parse('2026-08-02');

        $grossReqs = [
            $t2->toDateString() => '30',
        ];

        $rows = $this->service->computeRequirements($material->fresh(['inventory', 'inventoryParam']), $grossReqs, $t2);

        $this->assertCount(1, $rows);

        // NR = max(0, 30 - 10 - 0) = 20
        $this->assertSame('20.000000', $rows[0]['net_requirement']);

        // roundUpToEoq(20, 100) = ceil(20/100) * 100 = 100
        $this->assertSame('100.000000', $rows[0]['planned_order_release']);

        // release_date = t2 - 3 hari
        $this->assertSame($t2->copy()->subDays(3)->toDateString(), $rows[0]['release_date']);

        // POH sesudah = 10 + 0 - 30 = -20 (defisit, harus tetap tersimpan
        // sebagai negatif via roundSigned, bukan di-clamp ke 0).
        $this->assertSame('-20.000000', $rows[0]['projected_on_hand']);
    }

    /**
     * explodeBom(): qty WO dikalikan qty_per_unit tiap BOM line produk,
     * ditempatkan pada periode = tanggal $dueDate yang diberikan.
     */
    public function test_it_explodes_bom_correctly(): void
    {
        $product = Product::factory()->create();
        $materialA = Material::factory()->create();
        $materialB = Material::factory()->create();

        BillOfMaterial::create([
            'product_id'   => $product->id,
            'material_id'  => $materialA->id,
            'qty_per_unit' => '2.5',
            'unit'         => 'kg',
        ]);

        BillOfMaterial::create([
            'product_id'   => $product->id,
            'material_id'  => $materialB->id,
            'qty_per_unit' => '1.000000',
            'unit'         => 'pcs',
        ]);

        $workOrder = WorkOrder::factory()->create([
            'product_id' => $product->id,
            'qty'        => 10,
        ]);

        $dueDate = Carbon::parse('2026-08-15');

        $result = $this->service->explodeBom($workOrder->fresh('product.billOfMaterials'), $dueDate);

        // Material A: 10 x 2.5 = 25
        $this->assertSame('25.0000000000', $result[$materialA->id][$dueDate->toDateString()]);

        // Material B: 10 x 1 = 10
        $this->assertSame('10.0000000000', $result[$materialB->id][$dueDate->toDateString()]);
    }
    /**
     * checkReorderAlerts() skenario (a): qty_on_hand + qty_on_order <= rop
     * WAJIB memicu pembuatan ReorderAlert baru berstatus 'open', dengan
     * current_qty = qty_on_hand + qty_on_order (bukan qty_on_hand saja).
     */
    public function test_check_reorder_alerts_creates_alert_when_stock_at_or_below_rop(): void
    {
        $material = Material::factory()->create(['name' => 'Besi Plat 2mm']);

        Inventory::create([
            'material_id'  => $material->id,
            'qty_on_hand'  => 20,
            'qty_on_order' => 0,
            'last_updated' => now(),
        ]);

        InventoryParam::create([
            'material_id'                => $material->id,
            'annual_demand'              => 3650,
            'ordering_cost'              => 150000,
            'holding_cost_per_unit_year' => 5000,
            'lead_time_days'             => 3,
            'demand_std_dev'             => 3,
            'service_level_z'            => 1.6450,
            'eoq'                        => 467.9744,
            'rop'                        => 38.5477, // qty_on_hand (20) <= rop -> harus trigger alert
        ]);

        $created = $this->service->checkReorderAlerts();

        $this->assertCount(1, $created);

        $this->assertDatabaseHas('reorder_alerts', [
            'material_id' => $material->id,
            'status'      => 'open',
        ]);

        $alert = ReorderAlert::where('material_id', $material->id)->first();
        $this->assertSame('20.0000', $alert->current_qty);
    }

    /**
     * checkReorderAlerts() skenario (b): idempotency guard. Jika sudah
     * ada ReorderAlert status 'open' untuk material tsb, run kedua TIDAK
     * BOLEH membuat alert duplikat -- sesuai docs/inventory.md § Reorder
     * Alert Logic ("if no open alert for this material").
     */
    public function test_check_reorder_alerts_does_not_duplicate_when_open_alert_exists(): void
    {
        $material = Material::factory()->create(['name' => 'Besi Plat 2mm']);

        Inventory::create([
            'material_id'  => $material->id,
            'qty_on_hand'  => 20,
            'qty_on_order' => 0,
            'last_updated' => now(),
        ]);

        InventoryParam::create([
            'material_id'                => $material->id,
            'annual_demand'              => 3650,
            'ordering_cost'              => 150000,
            'holding_cost_per_unit_year' => 5000,
            'lead_time_days'             => 3,
            'demand_std_dev'             => 3,
            'service_level_z'            => 1.6450,
            'eoq'                        => 467.9744,
            'rop'                        => 38.5477,
        ]);

        // Run pertama: harus membuat 1 alert.
        $firstRun = $this->service->checkReorderAlerts();
        $this->assertCount(1, $firstRun);
        $this->assertDatabaseCount('reorder_alerts', 1);

        // Run kedua: kondisi stok belum berubah, alert 'open' masih ada
        // -> TIDAK BOLEH ada alert baru.
        $secondRun = $this->service->checkReorderAlerts();
        $this->assertCount(0, $secondRun);
        $this->assertDatabaseCount('reorder_alerts', 1);
    }

    /**
     * checkReorderAlerts() skenario (c): eoq_qty pada alert WAJIB
     * mengambil dari InventoryParam::eoq yang sudah tersimpan, bukan
     * dihitung ulang via EoqCalculatorService::computeEoq() -- fallback
     * hitung ulang hanya terjadi jika InventoryParam::eoq null (lihat
     * MrpService::checkReorderAlerts(): `$param->eoq ?? $this->eoq->
     * computeEoq($param)`). Test ini memastikan nilai EOQ yang SENGAJA
     * dipatok berbeda dari hasil formula tetap dipakai apa adanya.
     */
    public function test_check_reorder_alerts_uses_stored_eoq_not_recomputed(): void
    {
        $material = Material::factory()->create(['name' => 'Besi Plat 2mm']);

        Inventory::create([
            'material_id'  => $material->id,
            'qty_on_hand'  => 5,
            'qty_on_order' => 0,
            'last_updated' => now(),
        ]);

        // eoq dipatok manual ke nilai yang SENGAJA tidak sama dengan hasil
        // formula EOQ dari annual_demand/ordering_cost/holding_cost di
        // bawah ini (formula nyata akan menghasilkan angka lain sama
        // sekali) -- supaya test ini benar-benar membuktikan nilai stored
        // yang dipakai, bukan hasil hitung ulang.
        InventoryParam::create([
            'material_id'                => $material->id,
            'annual_demand'              => 3650,
            'ordering_cost'              => 150000,
            'holding_cost_per_unit_year' => 5000,
            'lead_time_days'             => 3,
            'demand_std_dev'             => 3,
            'service_level_z'            => 1.6450,
            'eoq'                        => '999.0000', // nilai patokan, bukan hasil formula
            'rop'                        => 100, // qty_on_hand (5) <= rop -> trigger
        ]);

        $this->service->checkReorderAlerts();

        $alert = ReorderAlert::where('material_id', $material->id)->first();

        $this->assertSame('999.0000', $alert->eoq_qty);
    }
}