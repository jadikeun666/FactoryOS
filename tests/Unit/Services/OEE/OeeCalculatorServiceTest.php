<?php

namespace Tests\Unit\Services\OEE;

use PHPUnit\Framework\Attributes\Test;

use App\Exceptions\InvalidProductionLogException;
use App\Models\ProductionLog;
use App\Services\OEE\OeeCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * @see docs/oee-formulas.md § Contoh Perhitungan Manual
 *
 * planned=480, downtime=60, actual_output=380, good_output=370, ict=1.0
 * Availability = 0.875000, Performance = 0.904762 (dibulatkan dari
 * 0.90476190...), Quality = 0.973684.
 *
 * CATATAN KOREKSI: docs/oee-formulas.md menyatakan hasil akhir OEE = 0.771099,
 * tapi 0.875000 × 0.904762 × 0.973684 secara matematis = 0.770833 (bukan
 * 0.771099 — sudah diverifikasi manual, tampaknya salah hitung di dokumen).
 * Test ini memakai nilai yang benar secara matematis. Perlu dikoreksi juga
 * di docs/oee-formulas.md dan docs/engineering-rules.md.
 *
 * Event::fake() dipakai supaya ProductionLogObserver (yang dispatch job
 * RecalculateOeeJob secara synchronous saat QUEUE_CONNECTION=sync) tidak
 * ikut memanggil compute() saat factory create() — kita mau menguji
 * OeeCalculatorService secara terisolasi.
 */
class OeeCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private OeeCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->service = new OeeCalculatorService();
    }

    #[Test]
    public function it_computes_oee_matching_manual_example(): void
    {
        $log = ProductionLog::factory()->create([
            'planned_minutes'          => 480,
            'downtime_minutes'         => 60,
            'actual_output'            => 380,
            'good_output'              => 370,
            'ideal_cycle_time_minutes' => 1.0,
        ]);

        $snapshot = $this->service->compute($log);

        $this->assertSame('0.875000', (string) $snapshot->availability);
        $this->assertSame('0.904762', (string) $snapshot->performance);
        $this->assertSame('0.973684', (string) $snapshot->quality);
        $this->assertSame('0.770833', (string) $snapshot->oee);

        $this->assertDatabaseHas('oee_snapshots', [
            'work_center_id' => $log->work_center_id,
            'shift_id'       => $log->shift_id,
        ]);
    }

    #[Test]
    public function it_caps_performance_at_one_when_output_exceeds_theoretical_max(): void
    {
        $log = ProductionLog::factory()->create([
            'planned_minutes'          => 480,
            'downtime_minutes'         => 0,
            'actual_output'            => 600,
            'good_output'              => 600,
            'ideal_cycle_time_minutes' => 1.0,
        ]);

        $snapshot = $this->service->compute($log);

        $this->assertSame('1.000000', (string) $snapshot->performance);
    }

    #[Test]
    public function it_throws_when_planned_minutes_is_zero(): void
    {
        $log = ProductionLog::factory()->create([
            'planned_minutes' => 0,
        ]);

        $this->expectException(InvalidProductionLogException::class);

        $this->service->compute($log);
    }

    #[Test]
    public function it_throws_when_actual_output_is_zero(): void
    {
        $log = ProductionLog::factory()->create([
            'actual_output' => 0,
            'good_output'   => 0,
        ]);

        $this->expectException(InvalidProductionLogException::class);

        $this->service->compute($log);
    }

    #[Test]
    public function it_throws_when_operating_time_is_zero(): void
    {
        $log = ProductionLog::factory()->create([
            'planned_minutes'  => 480,
            'downtime_minutes' => 480,
        ]);

        $this->expectException(InvalidProductionLogException::class);

        $this->service->compute($log);
    }
}