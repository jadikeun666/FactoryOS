<?php

namespace Tests\Unit\Services\Scheduling;

use App\Services\Scheduling\Algorithms\SptAlgorithm;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSchedulingFixtures;
use Tests\TestCase;

/**
 * @see docs/scheduling.md § Dispatching Rules > SPT
 * Score = std_process_time_minutes + setup_time_minutes (ascending)
 */
class SptAlgorithmTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchedulingFixtures;

    private SptAlgorithm $algorithm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->algorithm = new SptAlgorithm();
    }

    /** @test */
    public function it_scores_operation_as_process_time_plus_setup_time(): void
    {
        $wc = $this->makeWorkCenter('M01');
        $product = $this->makeProduct('PRD-SPT-01');
        $routing = $this->makeRouting($product, $wc, sequence: 1, processMinutes: 60, setupMinutes: 10);
        $wo = $this->makeWorkOrder($product, Carbon::now()->addDays(2));
        $op = $this->makeWoOperation($wo, $routing);

        $score = $this->algorithm->score($op, Carbon::now(), []);

        // 60 + 10 = 70, bcmath scale 6 sehingga hasilnya "70.000000"
        $this->assertSame('70.000000', $score);
    }

    /** @test */
    public function it_ranks_shorter_operation_with_smaller_score(): void
    {
        $wc = $this->makeWorkCenter('M02');
        $product = $this->makeProduct('PRD-SPT-02');

        $shortRouting = $this->makeRouting($product, $wc, sequence: 1, processMinutes: 15, setupMinutes: 0);
        $longRouting = $this->makeRouting($product, $wc, sequence: 2, processMinutes: 90, setupMinutes: 5);

        $wo = $this->makeWorkOrder($product, Carbon::now()->addDays(2));
        $shortOp = $this->makeWoOperation($wo, $shortRouting);
        $longOp = $this->makeWoOperation($wo, $longRouting);

        $shortScore = $this->algorithm->score($shortOp, Carbon::now(), []);
        $longScore = $this->algorithm->score($longOp, Carbon::now(), []);

        $this->assertTrue(bccomp($shortScore, $longScore, 6) < 0, 'Operasi dengan durasi lebih pendek harus punya score lebih kecil (prioritas lebih tinggi).');
    }

    /** @test */
    public function code_returns_spt(): void
    {
        $this->assertSame('spt', $this->algorithm->code());
    }
}