<?php

namespace Tests\Unit\Events;

use App\Events\OeeUpdated;
use App\Models\OeeSnapshot;
use App\Models\Shift;
use App\Models\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see docs/oee-formulas.md § Real-time Update Flow (Soketi)
 * Memastikan struktur payload broadcast cocok dengan yang akan di-consume
 * Vue Echo listener (OeeGauge.vue), supaya tidak ada breaking change diam-diam
 * saat frontend Engine 2 mulai dibangun.
 */
class OeeUpdatedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_broadcasts_on_correct_private_channel(): void
    {
        $workCenter = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $snapshot = OeeSnapshot::create([
            'work_center_id' => $workCenter->id,
            'log_date'       => now()->toDateString(),
            'shift_id'       => $shift->id,
            'availability'   => '0.875000',
            'performance'    => '0.904762',
            'quality'        => '0.973684',
            'oee'            => '0.770833',
            'computed_at'    => now(),
        ]);

        $event = new OeeUpdated($snapshot);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-work-center.' . $workCenter->id, $channels[0]->name);
    }

    /** @test */
    public function it_broadcasts_as_oee_updated(): void
    {
        $workCenter = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $snapshot = OeeSnapshot::create([
            'work_center_id' => $workCenter->id,
            'log_date'       => now()->toDateString(),
            'shift_id'       => $shift->id,
            'availability'   => '0.875000',
            'performance'    => '0.904762',
            'quality'        => '0.973684',
            'oee'            => '0.770833',
            'computed_at'    => now(),
        ]);

        $event = new OeeUpdated($snapshot);

        $this->assertSame('oee.updated', $event->broadcastAs());
    }

    /** @test */
    public function it_includes_expected_snapshot_fields_in_payload(): void
    {
        $workCenter = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $snapshot = OeeSnapshot::create([
            'work_center_id' => $workCenter->id,
            'log_date'       => '2026-07-15',
            'shift_id'       => $shift->id,
            'availability'   => '0.875000',
            'performance'    => '0.904762',
            'quality'        => '0.973684',
            'oee'            => '0.770833',
            'computed_at'    => now(),
        ]);

        $event = new OeeUpdated($snapshot);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('snapshot', $payload);

        $data = $payload['snapshot'];
        $this->assertSame($workCenter->id, $data['work_center_id']);
        $this->assertSame('2026-07-15', $data['log_date']);
        $this->assertSame($shift->id, $data['shift_id']);
        $this->assertSame('0.875000', $data['availability']);
        $this->assertSame('0.904762', $data['performance']);
        $this->assertSame('0.973684', $data['quality']);
        $this->assertSame('0.770833', $data['oee']);
        $this->assertArrayHasKey('computed_at', $data);
    }
}