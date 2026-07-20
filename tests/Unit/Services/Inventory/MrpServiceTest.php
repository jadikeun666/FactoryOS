<?php

namespace Tests\Unit\Services\Inventory;

use App\Models\BillOfMaterial;
use App\Models\Inventory;
use App\Models\InventoryParam;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Product;
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
}