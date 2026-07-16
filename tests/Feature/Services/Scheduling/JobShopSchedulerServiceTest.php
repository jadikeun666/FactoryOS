<?php

namespace Tests\Feature\Services\Scheduling;

use PHPUnit\Framework\Attributes\Test;

use App\Exceptions\SchedulingException;
use App\Models\WoOperation;
use App\Services\Scheduling\JobShopSchedulerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSchedulingFixtures;
use Tests\TestCase;

/**
 * Test ini me-replikasi PERSIS skenario "Contoh Walkthrough: 2 Mesin, 3 WO"
 * di docs/scheduling.md, supaya hasil algoritma bisa divalidasi terhadap
 * perhitungan manual yang sudah didokumentasikan di sana.
 *
 * Setup:
 *   Work Centers: M1, M2
 *   WO-A: due +2 hari, routing → [Op1: M1 (60 min), Op2: M2 (40 min)]
 *   WO-B: due +1 hari, routing → [Op1: M2 (30 min), Op2: M1 (90 min)]
 *   WO-C: due +3 hari, routing → [Op1: M1 (45 min)]
 *   Start time: 07:00
 */
class JobShopSchedulerServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchedulingFixtures;

    private JobShopSchedulerService $service;

    private Carbon $startFrom;

    private WoOperation $opA1;
    private WoOperation $opA2;
    private WoOperation $opB1;
    private WoOperation $opB2;
    private WoOperation $opC1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(JobShopSchedulerService::class);
        $this->startFrom = Carbon::create(2026, 1, 5, 7, 0, 0);

        $this->seedWalkthroughScenario();
    }

    private function seedWalkthroughScenario(): void
    {
        $m1 = $this->makeWorkCenter('M1');
        $m2 = $this->makeWorkCenter('M2');

        $productA = $this->makeProduct('PRD-WALK-A');
        $productB = $this->makeProduct('PRD-WALK-B');
        $productC = $this->makeProduct('PRD-WALK-C');

        $routingA1 = $this->makeRouting($productA, $m1, sequence: 1, processMinutes: 60);
        $routingA2 = $this->makeRouting($productA, $m2, sequence: 2, processMinutes: 40);
        $routingB1 = $this->makeRouting($productB, $m2, sequence: 1, processMinutes: 30);
        $routingB2 = $this->makeRouting($productB, $m1, sequence: 2, processMinutes: 90);
        $routingC1 = $this->makeRouting($productC, $m1, sequence: 1, processMinutes: 45);

        // release_date di masa lalu supaya job_ready_at = start_from untuk semua WO
        // (bukan dibatasi oleh release_date), sesuai edge case di docs/scheduling.md.
        $release = $this->startFrom->copy()->subDay();

        $woA = $this->makeWorkOrder($productA, $this->startFrom->copy()->addDays(2), $release);
        $woB = $this->makeWorkOrder($productB, $this->startFrom->copy()->addDay(), $release);
        $woC = $this->makeWorkOrder($productC, $this->startFrom->copy()->addDays(3), $release);

        $this->opA1 = $this->makeWoOperation($woA, $routingA1);
        $this->opA2 = $this->makeWoOperation($woA, $routingA2);
        $this->opB1 = $this->makeWoOperation($woB, $routingB1);
        $this->opB2 = $this->makeWoOperation($woB, $routingB2);
        $this->opC1 = $this->makeWoOperation($woC, $routingC1);
    }

    #[Test]
    public function spt_run_follows_manual_walkthrough_from_docs(): void
    {
        $schedule = $this->service->run('spt', $this->startFrom);

        $assignments = $schedule->assignments()->orderBy('start_at')->orderBy('id')->get();

        $this->assertCount(5, $assignments, 'Total assignments harus sama dengan total wo_operations (2+2+1).');

        // Iterasi 1: SPT → pilih WO-B/Op1 (30 min, terpendek di antara semua kandidat awal)
        $this->assertSame($this->opB1->id, $assignments[0]->wo_operation_id);
        $this->assertSame('2026-01-05 07:00:00', $assignments[0]->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-05 07:30:00', $assignments[0]->end_at->format('Y-m-d H:i:s'));

        // Iterasi 2: kandidat M1 (WO-A/Op1=60, WO-C/Op1=45) → SPT pilih WO-C/Op1 (45 min)
        $this->assertSame($this->opC1->id, $assignments[1]->wo_operation_id);
        $this->assertSame('2026-01-05 07:00:00', $assignments[1]->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-05 07:45:00', $assignments[1]->end_at->format('Y-m-d H:i:s'));

        // Iterasi 3: kandidat M1 tersisa (WO-A/Op1=60, WO-B/Op2=90) → SPT pilih WO-A/Op1 (60 min)
        $this->assertSame($this->opA1->id, $assignments[2]->wo_operation_id);
        $this->assertSame('2026-01-05 07:45:00', $assignments[2]->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-05 08:45:00', $assignments[2]->end_at->format('Y-m-d H:i:s'));

        // Iterasi 4 & 5: WO-A/Op2 (M2, 40 min) dan WO-B/Op2 (M1, 90 min) — keduanya
        // baru eligible setelah WO-A/Op1 & WO-B/Op1 selesai. SPT pilih yang lebih
        // pendek dulu (WO-A/Op2), keduanya start bersamaan di 08:45 (M2 & M1 sama-sama idle).
        $lastTwoOpIds = $assignments->slice(3, 2)->pluck('wo_operation_id')->sort()->values();
        $this->assertEqualsCanonicalizing([$this->opA2->id, $this->opB2->id], $lastTwoOpIds->all());

        $opA2Assignment = $assignments->firstWhere('wo_operation_id', $this->opA2->id);
        $opB2Assignment = $assignments->firstWhere('wo_operation_id', $this->opB2->id);

        $this->assertSame('2026-01-05 08:45:00', $opA2Assignment->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-05 09:25:00', $opA2Assignment->end_at->format('Y-m-d H:i:s')); // +40 min

        $this->assertSame('2026-01-05 08:45:00', $opB2Assignment->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-05 10:15:00', $opB2Assignment->end_at->format('Y-m-d H:i:s')); // +90 min

        // Makespan = rentang dari assignment paling awal (07:00) sampai paling akhir (10:15) = 195 menit
        $this->assertSame('195.00', $schedule->makespan_minutes);

        // Due date semua WO masih jauh di depan dibanding jadwal ini (hitungan hari,
        // sedangkan makespan hanya ~3 jam) → tidak ada yang terlambat.
        $this->assertSame('0.00', $schedule->total_tardiness_minutes);
        $this->assertSame(0, $schedule->late_wo_count);
    }

    #[Test]
    public function edd_run_prioritizes_earliest_due_date_regardless_of_machine(): void
    {
        $schedule = $this->service->run('edd', $this->startFrom);

        $firstAssignment = $schedule->assignments()->orderBy('start_at')->orderBy('id')->first();

        // Sesuai docs/scheduling.md: "EDD → WO-B due paling awal (1 hari) → pilih WO-B/Op1
        // (meski bukan di M1, EDD tetap prioritaskan WO dengan due date terdekat)"
        $this->assertSame($this->opB1->id, $firstAssignment->wo_operation_id);
    }

    #[Test]
    public function fifo_run_ignores_processing_time_and_due_date(): void
    {
        // FIFO murni berdasarkan created_at WO, tidak peduli durasi proses atau due date.
        // Karena WO-A dibuat lebih dulu di seedWalkthroughScenario(), FIFO harus
        // memprioritaskan operasi WO-A meskipun proses/due date-nya bukan yang tercepat.
        $schedule = $this->service->run('fifo', $this->startFrom);

        $firstAssignment = $schedule->assignments()->orderBy('start_at')->orderBy('id')->first();

        $this->assertSame($this->opA1->id, $firstAssignment->wo_operation_id);
    }

    #[Test]
    public function compare_all_runs_all_four_algorithms_and_returns_immutable_schedules(): void
    {
        $results = $this->service->compareAll($this->startFrom);

        $this->assertEqualsCanonicalizing(['spt', 'edd', 'cr', 'fifo'], array_keys($results));

        foreach ($results as $algorithmCode => $schedule) {
            $this->assertSame($algorithmCode, $schedule->algorithm);
            $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'algorithm' => $algorithmCode]);
            $this->assertGreaterThan(0, $schedule->assignments()->count());
        }

        // Setiap run harus menghasilkan record Schedule TERPISAH (immutable), bukan reuse.
        $scheduleIds = collect($results)->pluck('id');
        $this->assertSame($scheduleIds->count(), $scheduleIds->unique()->count());
    }

    #[Test]
    public function it_throws_scheduling_exception_when_no_eligible_candidate_remains(): void
    {
        // Simulasikan data rusak: WoOperation dengan sequence = 2 TANPA sequence = 1
        // pada work_order yang sama (bypass WoOperationGeneratorService secara sengaja).
        // Operasi ini tidak akan pernah eligible → harus memicu SchedulingException
        // sesuai docs/scheduling.md § Edge Cases dan step 2b pseudocode.
        $wc = $this->makeWorkCenter('M99');
        $product = $this->makeProduct('PRD-BROKEN');
        $routing = $this->makeRouting($product, $wc, sequence: 2, processMinutes: 30);

        $wo = $this->makeWorkOrder($product, $this->startFrom->copy()->addDay(), $this->startFrom->copy()->subDay());

        WoOperation::create([
            'work_order_id'  => $wo->id,
            'routing_id'     => $routing->id,
            'work_center_id' => $wc->id,
            'sequence'       => 2, // sengaja loncat, tidak ada sequence 1
            'status'         => 'pending',
        ]);

        $this->expectException(SchedulingException::class);

        $this->service->run('spt', $this->startFrom);
    }

    #[Test]
    public function compute_metrics_calculates_tardiness_and_flow_time_correctly(): void
    {
        $product = $this->makeProduct('PRD-METRICS');

        // WO release hari ini jam 00:00, due jam 09:00 hari yang sama.
        $release = Carbon::create(2026, 2, 1, 0, 0, 0);
        $due = Carbon::create(2026, 2, 1, 9, 0, 0);
        $wo = $this->makeWorkOrder($product, $due, $release);

        // Operasi selesai jam 10:00 → terlambat 60 menit dari due date (yang di-endOfDay-kan
        // oleh computeMetrics, karena due_date adalah kolom DATE tanpa jam spesifik).
        $plannedStart = Carbon::create(2026, 2, 1, 9, 0, 0);
        $plannedEnd = Carbon::create(2026, 2, 1, 10, 0, 0);

        $assignments = [
            [
                'wo_operation_id' => 1,
                'work_order_id'   => $wo->id,
                'work_center_id'  => 1,
                'planned_start'   => $plannedStart,
                'planned_end'     => $plannedEnd,
            ],
        ];

        $metrics = $this->service->computeMetrics($assignments, \App\Models\WorkOrder::whereKey($wo->id)->get());

        // Karena due_date di-treat sebagai endOfDay() (23:59:59) oleh computeMetrics,
        // operasi yang selesai jam 10:00 pada hari yang sama TIDAK dianggap terlambat.
        $this->assertSame('0.00', $metrics['total_tardiness_minutes']);
        $this->assertSame(0, $metrics['late_wo_count']);

        // flow_time = last_end (10:00) - release_date startOfDay (00:00) = 600 menit
        $this->assertSame('600.00', $metrics['mean_flow_time_minutes']);
    }

    #[Test]
    public function compute_metrics_returns_zeroed_result_for_empty_assignments(): void
    {
        $metrics = $this->service->computeMetrics([], \App\Models\WorkOrder::query()->whereRaw('1 = 0')->get());

        $this->assertSame('0.00', $metrics['makespan_minutes']);
        $this->assertSame('0.00', $metrics['total_tardiness_minutes']);
        $this->assertSame(0, $metrics['late_wo_count']);
        $this->assertSame('0.00', $metrics['mean_flow_time_minutes']);
    }
}