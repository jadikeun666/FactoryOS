<?php

namespace Tests\Feature\Observers;

use PHPUnit\Framework\Attributes\Test;

use App\Events\ProductionLogSaved;
use App\Jobs\RecalculateOeeJob;
use App\Models\ProductionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * @see docs/architecture.md § Observers, § Events & Listeners
 *
 * Menguji rantai: ProductionLog::create/update
 *   → ProductionLogObserver
 *   → ProductionLogSaved (event)
 *   → RecalculateOeeListener
 *   → RecalculateOeeJob (queued)
 *
 * Event::fake() dipakai untuk assert dispatch tanpa menjalankan listener
 * sungguhan (jadi tidak butuh OeeCalculatorService/broadcast beneran di sini).
 */
class ProductionLogObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_production_log_saved_when_log_is_created(): void
    {
        Event::fake([ProductionLogSaved::class]);

        $log = ProductionLog::factory()->create();

        Event::assertDispatched(ProductionLogSaved::class, function ($event) use ($log) {
            return $event->log->is($log);
        });
    }

    #[Test]
    public function it_dispatches_production_log_saved_when_unvalidated_log_is_updated(): void
    {
        $log = ProductionLog::factory()->create([
            'is_validated' => false,
        ]);

        Event::fake([ProductionLogSaved::class]);

        $log->update(['downtime_minutes' => 90]);

        Event::assertDispatched(ProductionLogSaved::class, function ($event) use ($log) {
            return $event->log->is($log);
        });
    }

    #[Test]
    public function it_does_not_dispatch_when_validated_log_is_updated(): void
    {
        $log = ProductionLog::factory()->create([
            'is_validated' => true,
        ]);

        Event::fake([ProductionLogSaved::class]);

        $log->update(['downtime_minutes' => 90]);

        Event::assertNotDispatched(ProductionLogSaved::class);
    }

    #[Test]
    public function saving_a_log_ultimately_queues_recalculate_oee_job(): void
    {
        // Tidak fake Event di sini — biarkan listener sungguhan jalan
        // (QUEUE_CONNECTION=sync di test env, jadi listener akan dispatch job),
        // tapi fake Bus/Queue supaya job tidak benar-benar dieksekusi
        // (menghindari ketergantungan pada broadcasting/OeeCalculatorService).
        \Illuminate\Support\Facades\Queue::fake();

        $log = ProductionLog::factory()->create();

        \Illuminate\Support\Facades\Queue::assertPushed(RecalculateOeeJob::class, function ($job) use ($log) {
            return $this->extractLogFromJob($job)->is($log);
        });
    }

    private function extractLogFromJob(RecalculateOeeJob $job): ProductionLog
    {
        $reflection = new \ReflectionProperty(RecalculateOeeJob::class, 'log');
        $reflection->setAccessible(true);

        return $reflection->getValue($job);
    }
}