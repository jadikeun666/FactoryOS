<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class OeeSnapshotFactory extends Factory
{
    public function definition(): array
    {
        $availability = '0.875000';
        $performance = '0.904762';
        $quality = '0.973684';

        return [
            'work_center_id' => WorkCenter::factory(),
            'shift_id' => Shift::factory(),
            'log_date' => now()->toDateString(),
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
            'oee' => bcmul(bcmul($availability, $performance, 6), $quality, 6),
            'computed_at' => now(),
        ];
    }
}
