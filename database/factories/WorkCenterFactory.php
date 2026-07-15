<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WorkCenterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Mesin ' . fake()->randomElement(['Bubut', 'Frais', 'Bor', 'Las', 'CNC']) . ' ' . fake()->numberBetween(1, 99),
            'code' => strtoupper(fake()->unique()->bothify('M##')),
            'capacity_per_shift_minutes' => 480,
            'setup_time_minutes' => fake()->randomElement([10, 15, 20, 30]),
            'is_active' => true,
            'description' => fake()->sentence(),
        ];
    }
}