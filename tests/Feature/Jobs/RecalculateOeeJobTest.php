<?php

namespace Tests\Feature\Jobs;

use PHPUnit\Framework\Attributes\Test;

use App\Events\OeeUpdated;
use App\Jobs\RecalculateOeeJob;
use App\Models\ProductionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * @see docs/architecture.md § Jobs (Queued) — RecalculateOeeJob
 * @see docs/oee-formulas.md § Real-time Update Flow (Soketi)
 */
class RecalculateOeeJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function handling_the_job_computes_snapshot_and_broadcasts_oee_updated(): void
    {
        // Cegah ProductionLogObserver ikut mendispatch job ini duluan saat factory create()
        Event::fake([\App\Events\ProductionLogSaved::class]);

        $log = ProductionLog::factory()->create([
            'planned_minutes'          => 480,
            'downtime_minutes'         => 60,
            'actual_output'            => 380,
            'good_output'              => 370,
            'ideal_cycle_time_minutes' => 1.0,
        ]);

        // Sekarang fake event broadcasting untuk assert OeeUpdated
        Event::fake([OeeUpdated::class]);

        $job = new RecalculateOeeJob($log);
        $job->handle(app(\App\Services\OEE\OeeCalculatorService::class));

        $this->assertDatabaseHas('oee_snapshots', [
            'work_center_id' => $log->work_center_id,
            'shift_id'       => $log->shift_id,
            'oee'            => '0.770833',
        ]);

        Event::assertDispatched(OeeUpdated::class, function ($event) use ($log) {
            return $event->snapshot->work_center_id === $log->work_center_id;
        });
    }
}