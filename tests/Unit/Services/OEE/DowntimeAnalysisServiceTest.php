<?php

namespace Tests\Unit\Services\OEE;

use App\Models\DowntimeEvent;
use App\Models\ProductionLog;
use App\Models\Shift;
use App\Models\WorkCenter;
use App\Services\OEE\DowntimeAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DowntimeAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private DowntimeAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DowntimeAnalysisService();
    }

    private function makeLog(WorkCenter $wc, Shift $shift, string $date): ProductionLog
    {
        return ProductionLog::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'log_date'       => $date,
        ]);
    }

    private function makeDowntime(ProductionLog $log, string $category, float $minutes, string $startedAt): DowntimeEvent
    {
        return DowntimeEvent::create([
            'production_log_id' => $log->id,
            'reason_category'   => $category,
            'reason_detail'     => null,
            'duration_minutes'  => $minutes,
            'started_at'        => $startedAt,
        ]);
    }

    /** @test */
    public function it_computes_pareto_downtime_correctly(): void
    {
        // Data mengikuti contoh di docs/oee-formulas.md § Pareto Analysis Downtime
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $log1 = $this->makeLog($wc, $shift, '2026-07-10');
        $log2 = $this->makeLog($wc, $shift, '2026-07-11');

        $this->makeDowntime($log1, 'breakdown', 480, '2026-07-10 08:00:00');
        $this->makeDowntime($log1, 'setup', 320, '2026-07-10 10:00:00');
        $this->makeDowntime($log2, 'material', 150, '2026-07-11 08:00:00');
        $this->makeDowntime($log2, 'operator', 80, '2026-07-11 09:00:00');
        $this->makeDowntime($log2, 'other', 33, '2026-07-11 10:00:00');

        $result = $this->service->paretoDowntime(
            Carbon::parse('2026-07-10'),
            Carbon::parse('2026-07-11'),
        );

        $this->assertCount(5, $result);

        // Urutan harus DESC by total_minutes
        $this->assertSame('breakdown', $result[0]['category']);
        $this->assertSame('setup', $result[1]['category']);
        $this->assertSame('material', $result[2]['category']);
        $this->assertSame('operator', $result[3]['category']);
        $this->assertSame('other', $result[4]['category']);

        // Total keseluruhan = 480+320+150+80+33 = 1063
        // Percentage breakdown = 480/1063*100 = 45.155221...
        $this->assertEquals('45.155221', $result[0]['percentage']);
        $this->assertEquals('45.155221', $result[0]['cumulative']);

        // Cumulative harus monoton naik dan berakhir di ~100
        $lastCumulative = (float) $result[4]['cumulative'];
        $this->assertEqualsWithDelta(100.0, $lastCumulative, 0.01);
    }

    /** @test */
    public function it_filters_by_work_center_when_provided(): void
    {
        $wc1 = WorkCenter::factory()->create();
        $wc2 = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $log1 = $this->makeLog($wc1, $shift, '2026-07-10');
        $log2 = $this->makeLog($wc2, $shift, '2026-07-10');

        $this->makeDowntime($log1, 'breakdown', 100, '2026-07-10 08:00:00');
        $this->makeDowntime($log2, 'setup', 200, '2026-07-10 08:00:00');

        $result = $this->service->paretoDowntime(
            Carbon::parse('2026-07-10'),
            Carbon::parse('2026-07-10'),
            $wc1->id,
        );

        $this->assertCount(1, $result);
        $this->assertSame('breakdown', $result[0]['category']);
        $this->assertEquals('100.000000', $result[0]['percentage']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_downtime_in_range(): void
    {
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();
        $this->makeLog($wc, $shift, '2026-07-10');
        // tidak ada downtime event sama sekali

        $result = $this->service->paretoDowntime(
            Carbon::parse('2026-07-10'),
            Carbon::parse('2026-07-10'),
        );

        $this->assertSame([], $result);
    }

    /** @test */
    public function it_excludes_downtime_outside_date_range(): void
    {
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $logInRange = $this->makeLog($wc, $shift, '2026-07-10');
        $logOutOfRange = $this->makeLog($wc, $shift, '2026-08-01');

        $this->makeDowntime($logInRange, 'breakdown', 100, '2026-07-10 08:00:00');
        $this->makeDowntime($logOutOfRange, 'setup', 999, '2026-08-01 08:00:00');

        $result = $this->service->paretoDowntime(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertCount(1, $result);
        $this->assertSame('breakdown', $result[0]['category']);
    }
}