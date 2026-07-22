<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Product;
use App\Models\Routing;
use App\Models\Shift;
use App\Models\WorkCenter;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use App\Models\Inventory;
use App\Models\InventoryParam;
use App\Services\WorkOrder\WoOperationGeneratorService;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Shift kerja
        Shift::factory()->create([
            'name' => 'Shift Pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
        ]);
        Shift::factory()->create([
            'name' => 'Shift Siang',
            'start_time' => '15:00:00',
            'end_time' => '23:00:00',
        ]);

        // 2. Work Centers (5 mesin)
        $workCenters = WorkCenter::factory()->count(5)->create();

        // 3. Materials (10 material)
        $materials = Material::factory()->count(10)->create();

        // 4. Products (3 produk)
        $products = Product::factory()->count(3)->create();

        // 5. BOM: tiap produk pakai 2-4 material acak
        foreach ($products as $product) {
            $selectedMaterials = $materials->random(fake()->numberBetween(2, 4));
            foreach ($selectedMaterials as $material) {
                $product->billOfMaterials()->create([
                    'material_id' => $material->id,
                    'qty_per_unit' => fake()->randomFloat(6, 0.5, 10),
                    'unit' => $material->unit,
                    'notes' => null,
                ]);
            }
        }

        // 6. Routing: tiap produk punya 2-3 operasi berurutan di mesin acak
        foreach ($products as $product) {
            $sequence = 1;
            $usedWorkCenters = $workCenters->random(fake()->numberBetween(2, 3));
            foreach ($usedWorkCenters as $wc) {
                Routing::create([
                    'product_id' => $product->id,
                    'sequence' => $sequence++,
                    'work_center_id' => $wc->id,
                    'std_process_time_minutes' => fake()->numberBetween(20, 120),
                    'setup_time_minutes' => fake()->numberBetween(5, 20),
                    'notes' => null,
                ]);
            }
        }

        // 7. Work Orders (15 WO) + generate wo_operations dari routing produk.
        //    Sebelumnya factory()->create() saja TIDAK men-generate
        //    wo_operations (proses itu terjadi di WorkOrderController::store(),
        //    bukan otomatis dari factory) -- akibatnya WO hasil seeder tidak
        //    bisa benar-benar dijadwalkan/di-MRP-kan sampai wo_operations
        //    di-generate manual. Ditemukan sesi 2026-07-22 saat verifikasi
        //    end-to-end MRP menghasilkan requirements kosong meski Schedule
        //    berhasil dibuat -- diperbaiki di sini (lihat claude.md § Utang
        //    Teknis).
        $workOrders = WorkOrder::factory()->count(15)->create();

        $operationGenerator = app(WoOperationGeneratorService::class);
        foreach ($workOrders as $workOrder) {
            $operationGenerator->generate($workOrder);
        }

        // 8. Inventory + InventoryParam untuk setiap material yang benar-benar
        //    dipakai di BOM (bukan seluruh 10 material -- material yang tidak
        //    dipakai BOM manapun tidak butuh data inventory untuk keperluan MRP).
        //    Sebelumnya hanya 1 material yang punya data ini (diisi manual via
        //    tinker), sehingga MrpService::run() menghasilkan
        //    net_requirement = gross_requirement penuh tanpa pembulatan EOQ
        //    untuk material lain (lihat claude.md § Utang Teknis item 5).
        //    firstOrCreate() dipakai supaya idempotent -- aman dijalankan ulang
        //    tanpa migrate:fresh, tidak menimpa data yang sudah ada.
        $materialIdsInBom = \App\Models\BillOfMaterial::query()
            ->distinct()
            ->pluck('material_id');

        foreach ($materialIdsInBom as $materialId) {
            $material = Material::find($materialId);
            if (! $material) {
                continue;
            }

            // Angka bervariasi per material (bukan seragam) supaya hasil MRP
            // grid bermakna untuk demo/testing -- annual_demand & lead_time
            // acak dalam rentang realistis, holding_cost mengikuti aturan
            // 20-30% x unit_cost sesuai docs/inventory.md § EOQ.
            $annualDemand = fake()->randomFloat(4, 800, 5000);
            $orderingCost = fake()->randomFloat(4, 75000, 300000);
            $holdingCostPct = fake()->randomFloat(2, 0.20, 0.30);
            $holdingCost = bcmul((string) $material->unit_cost, (string) $holdingCostPct, 4);
            $leadTimeDays = fake()->numberBetween(2, 14);
            $demandStdDev = fake()->randomFloat(4, 1, 8);

            $inventoryParam = InventoryParam::firstOrCreate(
                ['material_id' => $material->id],
                [
                    'annual_demand'              => $annualDemand,
                    'ordering_cost'              => $orderingCost,
                    'holding_cost_per_unit_year' => $holdingCost,
                    'lead_time_days'             => $leadTimeDays,
                    'demand_std_dev'             => $demandStdDev,
                    'service_level_z'            => 1.6450, // default 95%, sesuai docs/inventory.md
                ]
            );

            // Hitung & simpan EOQ/Safety Stock/ROP nyata via
            // EoqCalculatorService (bukan hardcode), supaya konsisten dengan
            // pola yang sudah dipakai untuk material id=4 sebelumnya.
            app(\App\Services\Inventory\EoqCalculatorService::class)
                ->computeAndSave($inventoryParam->fresh());

            // qty_on_hand bervariasi: sebagian besar cukup, sebagian sengaja
            // di bawah ROP supaya reorder alert punya kasus nyata untuk
            // ditest/didemokan (bukan semua material "aman").
            $inventoryParam->refresh();
            $rop = (float) ($inventoryParam->rop ?? 0);
            $qtyOnHand = fake()->boolean(30)
                ? fake()->randomFloat(4, 0, max($rop - 1, 1))   // 30% kasus: di bawah ROP
                : fake()->randomFloat(4, $rop + 1, $rop + 500); // 70% kasus: aman

            Inventory::firstOrCreate(
                ['material_id' => $material->id],
                [
                    'qty_on_hand'  => $qtyOnHand,
                    'qty_on_order' => 0,
                    'last_updated' => now(),
                ]
            );
        }

        $this->command->info(sprintf(
            'Seeding Engine 3 selesai: %d material (dari BOM) diberi Inventory + InventoryParam.',
            $materialIdsInBom->count()
        ));

        $this->command->info('Seeding selesai: 2 shift, 5 mesin, 10 material, 3 produk, 15 WO (dengan wo_operations)');
    }
}