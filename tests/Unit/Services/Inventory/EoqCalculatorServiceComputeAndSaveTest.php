<?php

namespace Tests\Unit\Services\Inventory;

use App\Models\InventoryParam;
use App\Services\Inventory\EoqCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see docs/inventory.md § EOQ, § Safety Stock & Reorder Point
 * @see docs/engineering-rules.md § 6 (testing policy)
 *
 * Test terpisah dari EoqCalculatorServiceTest.php (yang menguji formula
 * murni tanpa DB) karena computeAndSave() butuh RefreshDatabase untuk
 * persist ke InventoryParam -- konsisten dengan alasan yang dicatat di
 * claude.md § Utang Teknis.
 */
class EoqCalculatorServiceComputeAndSaveTest extends TestCase
{
    use RefreshDatabase;

    private EoqCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EoqCalculatorService();
    }

    #[Test]
    public function it_computes_and_persists_eoq_safety_stock_rop(): void
    {
        // Data sama dengan contoh manual docs/inventory.md supaya hasil
        // tersimpan bisa diverifikasi terhadap angka yang sudah diketahui.
        $params = InventoryParam::factory()->create([
            'annual_demand' => '3650',
            'ordering_cost' => '150000',
            'holding_cost_per_unit_year' => '5000',
            'lead_time_days' => 7,
            'demand_std_dev' => '3',
            'service_level_z' => '1.6450',
            'eoq' => null,
            'safety_stock' => null,
            'rop' => null,
            'last_computed_at' => null,
        ]);

        $result = $this->service->computeAndSave($params);

        // annual_demand=3650, ordering_cost=150000, holding_cost=5000
        // EOQ = sqrt(2*3650*150000/5000) = sqrt(219000) = 467.972221...
        $this->assertEqualsWithDelta(467.9744, (float) $result->eoq, 0.001);

        // Safety Stock & ROP identik dengan contoh manual yang sudah
        // diverifikasi di EoqCalculatorServiceTest (13.056783 & 83.056783).
        $this->assertEqualsWithDelta(13.056783, (float) $result->safety_stock, 0.0001);
        $this->assertEqualsWithDelta(83.056783, (float) $result->rop, 0.0001);

        $this->assertNotNull($result->last_computed_at);
    }

    #[Test]
    public function it_persists_changes_to_database_not_just_in_memory(): void
    {
        $params = InventoryParam::factory()->create([
            'eoq' => null,
            'safety_stock' => null,
            'rop' => null,
            'last_computed_at' => null,
        ]);

        $this->service->computeAndSave($params);

        // Ambil ulang dari DB (bukan dari objek yang sama) untuk pastikan
        // benar-benar tersimpan, bukan cuma berubah di memory.
        $fresh = InventoryParam::find($params->id);

        $this->assertNotNull($fresh->eoq);
        $this->assertNotNull($fresh->safety_stock);
        $this->assertNotNull($fresh->rop);
        $this->assertNotNull($fresh->last_computed_at);
    }

    #[Test]
    public function it_returns_refreshed_model_instance(): void
    {
        $params = InventoryParam::factory()->create(['eoq' => null]);

        $result = $this->service->computeAndSave($params);

        $this->assertInstanceOf(InventoryParam::class, $result);
        $this->assertSame($params->id, $result->id);
    }
}
