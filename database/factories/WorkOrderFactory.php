<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::inRandomOrder()->first()?->id ?? Product::factory(),
            'qty' => fake()->numberBetween(10, 200),
            'due_date' => fake()->dateTimeBetween('+1 day', '+14 days'),
            'priority' => fake()->numberBetween(1, 10),
            'release_date' => now(),
            'status' => 'draft',
            'notes' => null,
        ];
    }
}