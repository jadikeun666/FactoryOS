<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Poros Roda', 'Bracket Mesin', 'Plat Penutup', 'Rangka Besi', 'Komponen Gear']) . ' ' . fake()->word(),
            'sku' => strtoupper('PRD-' . fake()->unique()->bothify('###??')),
            'unit' => 'pcs',
            'description' => fake()->sentence(),
        ];
    }
}