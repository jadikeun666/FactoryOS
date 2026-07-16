<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'work_center_id'           => WorkCenter::factory(),
            'shift_id'                 => Shift::factory(),
            'log_date'                 => now()->toDateString(),
            'planned_minutes'          => 480,
            'downtime_minutes'         => 60,
            'actual_output'            => 380,
            'good_output'              => 370,
            'ideal_cycle_time_minutes' => 1.0,
            'is_validated'             => false,
            'validated_at'             => null,
            'created_by'               => User::factory(),
        ];
    }

    public function validated(): static
    {
        return $this->state(fn () => [
            'is_validated' => true,
            'validated_at' => now(),
        ]);
    }
}