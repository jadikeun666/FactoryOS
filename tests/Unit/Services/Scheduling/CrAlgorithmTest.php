<?php

namespace Tests\Unit\Services\Scheduling;

use App\Services\Scheduling\Algorithms\CrAlgorithm;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSchedulingFixtures;
use Tests\TestCase;

/**
 * @see docs/scheduling.md § Dispatching Rules > CR
 * Score = (due_date - now).inMinutes / total_remaining_processing_time (ascending)
 * Edge case: denominator = 0 → CR = 0 (prioritas tertinggi)
 */
class CrAlgorithmTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchedulingFixtures;

    private CrAlgorithm $algorithm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->algorithm = new CrAlgorithm();
    }

    /** @test */
    public function it_computes_critical_ratio_correctly(): void
    {
        $wc = $this->makeWorkCenter('M01');
        $product = $this->makeProduct('PRD-CR-01');
        $routing = $this->makeRouting($product, $wc, sequence: 1, processMinutes: 60);

        // PENTING: due_date adalah kolom DATE (bukan DATETIME) — komponen jam akan
        // hilang saat disimpan (WorkOrder::due_date selalu jadi 00:00:00 saat dibaca
        // kembali). $now sengaja dibuat tepat tengah malam supaya selisih ke due_date
        // besok jam 00:00 persis 1440 menit, tanpa terpengaruh pemotongan jam tsb.
        $now = Carbon::create(2026, 1, 1, 0, 0, 0);
        $wo = $this->makeWorkOrder($product, $now->copy()->addDay());
        $op = $this->makeWoOperation($wo, $routing);

        // total_remaining_processing_time = 720 menit (dari luar, disimulasikan)
        $remainingByWo = [$wo->id => '720'];

        $score = $this->algorithm->score($op, $now, $remainingByWo);

        // CR = 1440 / 720 = 2.0
        $this->assertSame('2.000000', $score);
    }

    /** @test */
    public function it_returns_zero_when_remaining_processing_time_is_zero(): void
    {
        $wc = $this->makeWorkCenter('M02');
        $product = $this->makeProduct('PRD-CR-02');
        $routing = $this->makeRouting($product, $wc, sequence: 1, processMinutes: 60);

        $now = Carbon::now();
        $wo = $this->makeWorkOrder($product, $now->copy()->addDay());
        $op = $this->makeWoOperation($wo, $routing);

        // Edge case sesuai docs/scheduling.md § Edge Cases:
        // "CR denominator = 0 (semua op selesai) → CR = 0, operasi ini prioritas tertinggi"
        $remainingByWo = [$wo->id => '0'];

        $score = $this->algorithm->score($op, $now, $remainingByWo);

        $this->assertSame('0.000000', $score);
    }

    /** @test */
    public function it_treats_missing_work_order_in_remaining_map_as_zero_denominator(): void
    {
        $wc = $this->makeWorkCenter('M03');
        $product = $this->makeProduct('PRD-CR-03');
        $routing = $this->makeRouting($product, $wc, sequence: 1, processMinutes: 60);

        $now = Carbon::now();
        $wo = $this->makeWorkOrder($product, $now->copy()->addDay());
        $op = $this->makeWoOperation($wo, $routing);

        // work_order_id sengaja tidak ada di $remainingByWo
        $score = $this->algorithm->score($op, $now, []);

        $this->assertSame('0.000000', $score);
    }

    /** @test */
    public function it_ranks_more_critical_work_order_with_smaller_score(): void
    {
        $wc = $this->makeWorkCenter('M04');
        $productA = $this->makeProduct('PRD-CR-A');
        $productB = $this->makeProduct('PRD-CR-B');

        $routingA = $this->makeRouting($productA, $wc, sequence: 1, processMinutes: 60);
        $routingB = $this->makeRouting($productB, $wc, sequence: 1, processMinutes: 60);

        $now = Carbon::now();

        // WO-A: due date dekat + sisa proses besar → CR kecil (kritis)
        $woA = $this->makeWorkOrder($productA, $now->copy()->addHours(2));
        // WO-B: due date jauh + sisa proses kecil → CR besar (santai)
        $woB = $this->makeWorkOrder($productB, $now->copy()->addDays(5));

        $opA = $this->makeWoOperation($woA, $routingA);
        $opB = $this->makeWoOperation($woB, $routingB);

        $remainingByWo = [
            $woA->id => '600',  // 10 jam sisa kerja, due dalam 2 jam → sangat kritis
            $woB->id => '60',   // 1 jam sisa kerja, due dalam 5 hari → sangat santai
        ];

        $scoreA = $this->algorithm->score($opA, $now, $remainingByWo);
        $scoreB = $this->algorithm->score($opB, $now, $remainingByWo);

        $this->assertTrue(bccomp($scoreA, $scoreB, 6) < 0, 'WO yang lebih kritis (CR kecil) harus diprioritaskan.');
    }

    /** @test */
    public function code_returns_cr(): void
    {
        $this->assertSame('cr', $this->algorithm->code());
    }
}