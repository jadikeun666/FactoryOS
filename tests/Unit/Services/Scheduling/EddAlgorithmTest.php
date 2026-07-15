<?php

namespace Tests\Unit\Services\Scheduling;

use App\Services\Scheduling\Algorithms\EddAlgorithm;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSchedulingFixtures;
use Tests\TestCase;

/**
 * @see docs/scheduling.md § Dispatching Rules > EDD
 * Score = work_order.due_date (ascending)
 */
class EddAlgorithmTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchedulingFixtures;

    private EddAlgorithm $algorithm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->algorithm = new EddAlgorithm();
    }

    /** @test */
    public function it_scores_operation_as_due_date_timestamp(): void
    {
        $wc = $this->makeWorkCenter('M01');
        $product = $this->makeProduct('PRD-EDD-01');
        $routing = $this->makeRouting($product, $wc, sequence: 1, processMinutes: 60);

        $dueDate = Carbon::now()->addDays(3);
        $wo = $this->makeWorkOrder($product, $dueDate);
        $op = $this->makeWoOperation($wo, $routing);

        $score = $this->algorithm->score($op, Carbon::now(), []);

        $this->assertSame((string) Carbon::parse($wo->due_date)->timestamp, $score);
    }

    /** @test */
    public function it_ranks_earlier_due_date_with_smaller_score(): void
    {
        $wc = $this->makeWorkCenter('M02');
        $productA = $this->makeProduct('PRD-EDD-A');
        $productB = $this->makeProduct('PRD-EDD-B');

        $routingA = $this->makeRouting($productA, $wc, sequence: 1, processMinutes: 60);
        $routingB = $this->makeRouting($productB, $wc, sequence: 1, processMinutes: 60);

        // WO-A due lebih cepat (1 hari) dibanding WO-B (5 hari)
        $woA = $this->makeWorkOrder($productA, Carbon::now()->addDay());
        $woB = $this->makeWorkOrder($productB, Carbon::now()->addDays(5));

        $opA = $this->makeWoOperation($woA, $routingA);
        $opB = $this->makeWoOperation($woB, $routingB);

        $scoreA = $this->algorithm->score($opA, Carbon::now(), []);
        $scoreB = $this->algorithm->score($opB, Carbon::now(), []);

        $this->assertTrue(bccomp($scoreA, $scoreB, 6) < 0, 'WO dengan due date lebih awal harus punya score lebih kecil.');
    }

    /** @test */
    public function code_returns_edd(): void
    {
        $this->assertSame('edd', $this->algorithm->code());
    }
}