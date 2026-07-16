<?php

namespace Tests\Unit\Services\OEE;

use App\Models\OeeSnapshot;
use App\Models\Shift;
use App\Models\WorkCenter;
use App\Services\OEE\OeeCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OeeCalculatorServiceTrendAndBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private OeeCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OeeCalculatorService();
    }

    private function makeSnapshot(WorkCenter $wc, Shift $shift, string $date, string $availability, string $performance, string $quality, string $oee): OeeSnapshot
    {
        return OeeSnapshot::create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'log_date'       => $date,
            'availability'   => $availability,
            'performance'    => $performance,
            'quality'        => $quality,
            'oee'            => $oee,
            'computed_at'    => now(),
        ]);
    }

    /** @test */
    public function it_averages_multiple_shifts_on_same_date(): void
    {
        $wc = WorkCenter::factory()->create();
        $shift1 = Shift::factory()->create();
        $shift2 = Shift::factory()->create();

        // Dua shift, tanggal sama — rata-rata harus dihitung
        $this->makeSnapshot($wc, $shift1, '2026-07-10', '0.875000', '0.904762', '0.973684', '0.770833');
        $this->makeSnapshot($wc, $shift2, '2026-07-10', '0.925000', '0.950000', '0.990000', '0.870469');

        $result = $this->service->trendData($wc->id, Carbon::parse('2026-07-10'), Carbon::parse('2026-07-10'));

        $this->assertCount(1, $result);
        $this->assertSame('2026-07-10', $result[0]['date']);

        // avg availability = (0.875000 + 0.925000) / 2 = 0.900000
        $this->assertSame('0.900000', $result[0]['availability']);
    }

    /** @test */
    public function it_returns_one_row_per_date_ordered_ascending(): void
    {
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->makeSnapshot($wc, $shift, '2026-07-12', '0.800000', '0.800000', '0.800000', '0.512000');
        $this->makeSnapshot($wc, $shift, '2026-07-10', '0.900000', '0.900000', '0.900000', '0.729000');
        $this->makeSnapshot($wc, $shift, '2026-07-11', '0.850000', '0.850000', '0.850000', '0.614125');

        $result = $this->service->trendData($wc->id, Carbon::parse('2026-07-10'), Carbon::parse('2026-07-12'));

        $this->assertCount(3, $result);
        $this->assertSame('2026-07-10', $result[0]['date']);
        $this->assertSame('2026-07-11', $result[1]['date']);
        $this->assertSame('2026-07-12', $result[2]['date']);
    }

    /** @test */
    public function it_excludes_other_work_centers(): void
    {
        $wc1 = WorkCenter::factory()->create();
        $wc2 = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $this->makeSnapshot($wc1, $shift, '2026-07-10', '0.900000', '0.900000', '0.900000', '0.729000');
        $this->makeSnapshot($wc2, $shift, '2026-07-10', '0.100000', '0.100000', '0.100000', '0.001000');

        $result = $this->service->trendData($wc1->id, Carbon::parse('2026-07-10'), Carbon::parse('2026-07-10'));

        $this->assertCount(1, $result);
        $this->assertSame('0.900000', $result[0]['availability']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_snapshot_in_range(): void
    {
        $wc = WorkCenter::factory()->create();

        $result = $this->service->trendData($wc->id, Carbon::parse('2026-07-10'), Carbon::parse('2026-07-10'));

        $this->assertSame([], $result);
    }

    /** @test */
    public function it_computes_benchmark_gap_correctly(): void
    {
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        // Data persis dari contoh manual docs/oee-formulas.md (hasil terkoreksi 0.770833)
        $snapshot = $this->makeSnapshot($wc, $shift, '2026-07-10', '0.875000', '0.904762', '0.973684', '0.770833');

        $result = $this->service->benchmarkVsWorldClass($snapshot);

        $this->assertSame('0.770833', $result['oee']['actual']);
        $this->assertSame('0.850000', $result['oee']['world_class']);
        $this->assertSame('-0.079167', $result['oee']['gap']);

        $this->assertSame('0.900000', $result['availability']['world_class']);
        $this->assertSame('-0.025000', $result['availability']['gap']);

        $this->assertSame('0.950000', $result['performance']['world_class']);
        $this->assertSame('-0.045238', $result['performance']['gap']);

        $this->assertSame('0.999900', $result['quality']['world_class']);
        $this->assertSame('-0.026216', $result['quality']['gap']);
    }

    /** @test */
    public function it_computes_positive_gap_when_actual_exceeds_world_class(): void
    {
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $snapshot = $this->makeSnapshot($wc, $shift, '2026-07-10', '0.950000', '0.980000', '0.999999', '0.930699');

        $result = $this->service->benchmarkVsWorldClass($snapshot);

        $this->assertSame('0.050000', $result['availability']['gap']);
        $this->assertSame('0.030000', $result['performance']['gap']);
    }
}