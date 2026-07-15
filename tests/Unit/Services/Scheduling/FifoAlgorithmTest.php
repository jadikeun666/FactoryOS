<?php

namespace Tests\Unit\Services\Scheduling;

use App\Services\Scheduling\Algorithms\FifoAlgorithm;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSchedulingFixtures;
use Tests\TestCase;

/**
 * @see docs/scheduling.md § Dispatching Rules > FIFO
 * Score = work_order.created_at (ascending)
 */
class FifoAlgorithmTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchedulingFixtures;

    private FifoAlgorithm $algorithm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->algorithm = new FifoAlgorithm();
    }

    /** @test */
    public function it_scores_operation_as_created_at_timestamp(): void
    {
        $wc = $this->makeWorkCenter('M01');
        $product = $this->makeProduct('PRD-FIFO-01');
        $routing = $this->makeRouting($product, $wc, sequence: 1, processMinutes: 60);

        $createdAt = Carbon::create(2026, 1, 1, 8, 0, 0);
        $wo = $this->makeWorkOrder($product, Carbon::now()->addDays(2), createdAt: $createdAt);
        $op = $this->makeWoOperation($wo, $routing);

        $score = $this->algorithm->score($op, Carbon::now(), []);

        $this->assertSame((string) $createdAt->timestamp, $score);
    }

    /** @test */
    public function it_ranks_earlier_created_work_order_with_smaller_score(): void
    {
        $wc = $this->makeWorkCenter('M02');
        $productA = $this->makeProduct('PRD-FIFO-A');
        $productB = $this->makeProduct('PRD-FIFO-B');

        $routingA = $this->makeRouting($productA, $wc, sequence: 1, processMinutes: 60);
        $routingB = $this->makeRouting($productB, $wc, sequence: 1, processMinutes: 60);

        $dueDate = Carbon::now()->addDays(2);
        $woA = $this->makeWorkOrder($productA, $dueDate, createdAt: Carbon::create(2026, 1, 1, 8, 0, 0));
        $woB = $this->makeWorkOrder($productB, $dueDate, createdAt: Carbon::create(2026, 1, 1, 9, 0, 0));

        $opA = $this->makeWoOperation($woA, $routingA);
        $opB = $this->makeWoOperation($woB, $routingB);

        $scoreA = $this->algorithm->score($opA, Carbon::now(), []);
        $scoreB = $this->algorithm->score($opB, Carbon::now(), []);

        $this->assertTrue(bccomp($scoreA, $scoreB, 6) < 0, 'WO yang dibuat lebih awal harus punya score lebih kecil.');
    }

    /** @test */
    public function code_returns_fifo(): void
    {
        $this->assertSame('fifo', $this->algorithm->code());
    }
}