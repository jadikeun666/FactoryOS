<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Product;
use App\Models\Routing;
use App\Models\Shift;
use App\Models\WorkCenter;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

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

        // 7. Work Orders (15 WO)
        WorkOrder::factory()->count(15)->create();

        $this->command->info('Seeding selesai: 2 shift, 5 mesin, 10 material, 3 produk, 15 WO');
    }
}