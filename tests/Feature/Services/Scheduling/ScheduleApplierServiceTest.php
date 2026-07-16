<?php

namespace Tests\Feature\Services\Scheduling;

use App\Exceptions\ScheduleApplyException;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Scheduling\ScheduleApplierService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSchedulingFixtures;
use Tests\TestCase;

/**
 * Memverifikasi ScheduleApplierService terhadap
 * App\Services\WorkOrder\WorkOrderStatusService::transition() yang
 * sesungguhnya (matrix ALLOWED_TRANSITIONS: draft → scheduled).
 */
class ScheduleApplierServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchedulingFixtures;

    private ScheduleApplierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ScheduleApplierService::class);
    }

    /** @test */
    public function it_applies_schedule_to_pending_operations_and_transitions_work_order_to_scheduled(): void
    {
        $workCenter = $this->makeWorkCenter('M01');
        $product = $this->makeProduct('PRD-APPLY-01');
        $routing = $this->makeRouting($product, $workCenter, 1, 90, 0);

        $workOrder = $this->makeWorkOrder($product, Carbon::now()->addDay());
        $this->assertSame('draft', $workOrder->status);

        $operation = $this->makeWoOperation($workOrder, $routing);
        $this->assertNull($operation->planned_start);

        $schedule = Schedule::create([
            'algorithm' => 'cr',
            'makespan_minutes' => 90,
            'total_tardiness_minutes' => 0,
            'late_wo_count' => 0,
            'mean_flow_time_minutes' => 90,
            'scheduled_from' => Carbon::now(),
        ]);

        $start = Carbon::now();
        $end = $start->copy()->addMinutes(90);

        ScheduleAssignment::create([
            'schedule_id' => $schedule->id,
            'wo_operation_id' => $operation->id,
            'work_center_id' => $workCenter->id,
            'start_at' => $start,
            'end_at' => $end,
            'slot_index' => 1,
        ]);

        $result = $this->service->apply($schedule->id);

        $this->assertEquals($schedule->id, $result['schedule_id']);
        $this->assertEquals(1, $result['updated_operations']);
        $this->assertEmpty($result['skipped_operation_ids']);
        $this->assertContains($workOrder->id, $result['transitioned_work_order_ids']);

        $operation->refresh();
        $this->assertNotNull($operation->planned_start);
        $this->assertNotNull($operation->planned_end);
        $this->assertEquals($start->toDateTimeString(), Carbon::parse($operation->planned_start)->toDateTimeString());
        $this->assertEquals($end->toDateTimeString(), Carbon::parse($operation->planned_end)->toDateTimeString());

        $workOrder->refresh();
        $this->assertEquals('scheduled', $workOrder->status);
    }

    /** @test */
    public function it_skips_operations_that_are_no_longer_pending(): void
    {
        $workCenter = $this->makeWorkCenter('M02');
        $product = $this->makeProduct('PRD-APPLY-02');
        $routing = $this->makeRouting($product, $workCenter, 1, 60, 0);

        $workOrder = $this->makeWorkOrder($product, Carbon::now()->addDay());
        $operation = $this->makeWoOperation($workOrder, $routing);
        $operation->forceFill(['status' => 'running'])->save();

        $schedule = Schedule::create([
            'algorithm' => 'spt',
            'makespan_minutes' => 60,
            'total_tardiness_minutes' => 0,
            'late_wo_count' => 0,
            'mean_flow_time_minutes' => 60,
            'scheduled_from' => Carbon::now(),
        ]);

        ScheduleAssignment::create([
            'schedule_id' => $schedule->id,
            'wo_operation_id' => $operation->id,
            'work_center_id' => $workCenter->id,
            'start_at' => Carbon::now(),
            'end_at' => Carbon::now()->addMinutes(60),
            'slot_index' => 1,
        ]);

        $this->expectException(ScheduleApplyException::class);

        $this->service->apply($schedule->id);
    }

    /** @test */
    public function it_throws_when_schedule_has_no_assignments(): void
    {
        $schedule = Schedule::create([
            'algorithm' => 'fifo',
            'makespan_minutes' => 0,
            'total_tardiness_minutes' => 0,
            'late_wo_count' => 0,
            'mean_flow_time_minutes' => 0,
            'scheduled_from' => Carbon::now(),
        ]);

        $this->expectException(ScheduleApplyException::class);

        $this->service->apply($schedule->id);
    }

    /** @test */
    public function it_throws_when_schedule_does_not_exist(): void
    {
        $this->expectException(ScheduleApplyException::class);

        $this->service->apply(99999);
    }
}