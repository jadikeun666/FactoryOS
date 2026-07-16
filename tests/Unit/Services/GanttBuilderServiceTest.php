<?php

namespace Tests\Unit\Services;

use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Services\Scheduling\GanttBuilderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSchedulingFixtures;
use Tests\TestCase;

class GanttBuilderServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchedulingFixtures;

    private GanttBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(GanttBuilderService::class);
    }

    /** @test */
    public function it_builds_gantt_structure_matching_docs_format(): void
    {
        $workCenter = $this->makeWorkCenter('M01');
        $product = $this->makeProduct('PRD-GANTT-01');
        $routing = $this->makeRouting($product, $workCenter, 1, 90, 0);

        $workOrder = $this->makeWorkOrder($product, Carbon::now()->addDay());
        $operation = $this->makeWoOperation($workOrder, $routing);

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

        $result = $this->service->build($schedule->fresh());

        // Struktur top-level sesuai docs/gantt.md
        $this->assertArrayHasKey('schedule', $result);
        $this->assertArrayHasKey('work_centers', $result);
        $this->assertArrayHasKey('work_orders', $result);
        $this->assertArrayHasKey('assignments', $result);

        // schedule summary
        $this->assertEquals($schedule->id, $result['schedule']['id']);
        $this->assertEquals('cr', $result['schedule']['algorithm']);
        $this->assertArrayHasKey('makespan_minutes', $result['schedule']);
        $this->assertArrayHasKey('total_tardiness_minutes', $result['schedule']);
        $this->assertArrayHasKey('late_wo_count', $result['schedule']);
        $this->assertArrayHasKey('mean_flow_time_minutes', $result['schedule']);
        $this->assertArrayHasKey('scheduled_from', $result['schedule']);

        // work_centers
        $this->assertCount(1, $result['work_centers']);
        $this->assertEquals('M01', $result['work_centers'][0]['code']);

        // work_orders
        $this->assertCount(1, $result['work_orders']);
        $wo = $result['work_orders'][0];
        $this->assertArrayHasKey('id', $wo);
        $this->assertArrayHasKey('name', $wo);
        $this->assertArrayHasKey('product', $wo);
        $this->assertArrayHasKey('due_date', $wo);
        $this->assertArrayHasKey('is_late', $wo);
        $this->assertFalse($wo['is_late'], 'WO selesai dalam 90 menit, due date besok — tidak seharusnya telat');

        // assignments
        $this->assertCount(1, $result['assignments']);
        $assignment = $result['assignments'][0];
        foreach ([
            'wo_operation_id', 'work_order_id', 'work_order_name',
            'work_center_id', 'work_center_name', 'sequence',
            'start_at', 'end_at', 'duration_minutes', 'is_late',
        ] as $key) {
            $this->assertArrayHasKey($key, $assignment, "Missing key: {$key}");
        }

        $this->assertEquals(90, $assignment['duration_minutes']);
        $this->assertFalse($assignment['is_late']);
    }

    /** @test */
    public function it_marks_work_order_and_assignment_as_late_when_planned_end_exceeds_due_date(): void
    {
        $workCenter = $this->makeWorkCenter('M02');
        $product = $this->makeProduct('PRD-GANTT-02');
        $routing = $this->makeRouting($product, $workCenter, 1, 120, 0);

        // due_date kemarin -> pasti telat
        $workOrder = $this->makeWorkOrder($product, Carbon::now()->subDay());
        $operation = $this->makeWoOperation($workOrder, $routing);

        $schedule = Schedule::create([
            'algorithm' => 'edd',
            'makespan_minutes' => 120,
            'total_tardiness_minutes' => 120,
            'late_wo_count' => 1,
            'mean_flow_time_minutes' => 120,
            'scheduled_from' => Carbon::now(),
        ]);

        $start = Carbon::now();
        $end = $start->copy()->addMinutes(120);

        ScheduleAssignment::create([
            'schedule_id' => $schedule->id,
            'wo_operation_id' => $operation->id,
            'work_center_id' => $workCenter->id,
            'start_at' => $start,
            'end_at' => $end,
            'slot_index' => 1,
        ]);

        $result = $this->service->build($schedule->fresh());

        $this->assertTrue($result['work_orders'][0]['is_late']);
        $this->assertTrue($result['assignments'][0]['is_late']);
    }
}