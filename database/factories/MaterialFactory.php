<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Besi Plat 2mm', 'Baut M8', 'Kawat Las', 'Cat Primer', 'Aluminium Batang']) . ' ' . fake()->word(),
            'sku' => strtoupper('MTR-' . fake()->unique()->bothify('###??')),
            'unit' => fake()->randomElement(['pcs', 'lembar', 'kg', 'meter']),
            'unit_cost' => fake()->randomFloat(4, 5000, 200000),
            'description' => fake()->sentence(),
        ];
    }
}