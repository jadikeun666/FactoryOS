<?php

namespace Tests\Feature\Controllers;

use PHPUnit\Framework\Attributes\Test;

use App\Models\DowntimeEvent;
use App\Models\ProductionLog;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DowntimeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(User $creator, bool $validated = false): ProductionLog
    {
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        return ProductionLog::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'is_validated'   => $validated,
            'created_by'     => $creator->id,
        ]);
    }

    #[Test]
    public function creator_can_add_downtime_event_to_unvalidated_log(): void
    {
        $user = User::factory()->create();
        $log = $this->makeLog($user);

        $response = $this->actingAs($user)->post(
            route('production-logs.downtime-events.store', $log),
            [
                'reason_category'  => 'setup',
                'reason_detail'    => 'Ganti cetakan',
                'duration_minutes' => 30,
                'started_at'       => '2026-07-10 09:00:00',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('downtime_events', [
            'production_log_id' => $log->id,
            'reason_category'    => 'setup',
        ]);
    }

    #[Test]
    public function cannot_add_downtime_event_to_validated_log(): void
    {
        $user = User::factory()->create();
        $log = $this->makeLog($user, validated: true);

        $response = $this->actingAs($user)->post(
            route('production-logs.downtime-events.store', $log),
            [
                'reason_category'  => 'setup',
                'duration_minutes' => 30,
                'started_at'       => '2026-07-10 09:00:00',
            ]
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('downtime_events', 0);
    }

    #[Test]
    public function creator_can_update_downtime_event(): void
    {
        $user = User::factory()->create();
        $log = $this->makeLog($user);

        $event = DowntimeEvent::create([
            'production_log_id' => $log->id,
            'reason_category'   => 'material',
            'reason_detail'     => null,
            'duration_minutes'  => 20,
            'started_at'        => '2026-07-10 10:00:00',
        ]);

        $response = $this->actingAs($user)->patch(
            route('production-logs.downtime-events.update', [$log, $event]),
            [
                'reason_category'  => 'material',
                'duration_minutes' => 45,
                'started_at'       => '2026-07-10 10:00:00',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('downtime_events', [
            'id'               => $event->id,
            'duration_minutes' => 45,
        ]);
    }

    #[Test]
    public function creator_can_delete_downtime_event(): void
    {
        $user = User::factory()->create();
        $log = $this->makeLog($user);

        $event = DowntimeEvent::create([
            'production_log_id' => $log->id,
            'reason_category'   => 'other',
            'reason_detail'     => null,
            'duration_minutes'  => 10,
            'started_at'        => '2026-07-10 11:00:00',
        ]);

        $response = $this->actingAs($user)->delete(
            route('production-logs.downtime-events.destroy', [$log, $event])
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('downtime_events', ['id' => $event->id]);
    }

    #[Test]
    public function stranger_cannot_add_downtime_event(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $log = $this->makeLog($owner);

        $response = $this->actingAs($stranger)->post(
            route('production-logs.downtime-events.store', $log),
            [
                'reason_category'  => 'operator',
                'duration_minutes' => 15,
                'started_at'       => '2026-07-10 12:00:00',
            ]
        );

        $response->assertForbidden();
    }
}