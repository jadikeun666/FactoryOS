<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReorderAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'current_qty' => fake()->randomFloat(4, 5, 50),
            'rop_qty' => fake()->randomFloat(4, 50, 100),
            'eoq_qty' => fake()->randomFloat(4, 100, 300),
            'status' => 'open',
        ];
    }
}
