<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryParamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'annual_demand' => fake()->randomFloat(4, 500, 5000),
            'ordering_cost' => fake()->randomFloat(4, 50000, 300000),
            'holding_cost_per_unit_year' => fake()->randomFloat(4, 1000, 10000),
            'lead_time_days' => fake()->numberBetween(1, 14),
            'demand_std_dev' => fake()->randomFloat(4, 1, 10),
            'service_level_z' => '1.6450',
            'eoq' => null,
            'safety_stock' => null,
            'rop' => null,
            'last_computed_at' => null,
        ];
    }
}
