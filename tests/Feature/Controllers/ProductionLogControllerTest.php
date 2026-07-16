<?php

namespace Tests\Feature\Controllers;

use PHPUnit\Framework\Attributes\Test;

use App\Models\ProductionLog;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    #[Test]
    public function authenticated_user_can_create_production_log_with_downtime_events(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->post(route('production-logs.store'), [
            'work_center_id'           => $wc->id,
            'shift_id'                 => $shift->id,
            'log_date'                 => '2026-07-10',
            'planned_minutes'          => 480,
            'downtime_minutes'         => 60,
            'actual_output'            => 380,
            'good_output'              => 370,
            'ideal_cycle_time_minutes' => 1.0,
            'downtime_events' => [
                [
                    'reason_category'  => 'breakdown',
                    'reason_detail'    => 'Motor terbakar',
                    'duration_minutes' => 60,
                    'started_at'       => '2026-07-10 08:00:00',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_logs', [
            'work_center_id' => $wc->id,
            'created_by'     => $user->id,
        ]);
        $this->assertDatabaseHas('downtime_events', [
            'reason_category' => 'breakdown',
        ]);
    }

    #[Test]
    public function store_fails_validation_when_good_output_exceeds_actual_output(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->post(route('production-logs.store'), [
            'work_center_id'           => $wc->id,
            'shift_id'                 => $shift->id,
            'log_date'                 => '2026-07-10',
            'planned_minutes'          => 480,
            'actual_output'            => 100,
            'good_output'              => 200, // invalid: melebihi actual_output
            'ideal_cycle_time_minutes' => 1.0,
        ]);

        $response->assertSessionHasErrors('good_output');
        $this->assertDatabaseCount('production_logs', 0);
    }

    #[Test]
    public function creator_can_update_unvalidated_log(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $log = ProductionLog::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'is_validated'   => false,
            'created_by'     => $user->id,
        ]);

        $response = $this->actingAs($user)->patch(route('production-logs.update', $log), [
            'actual_output' => 400,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_logs', [
            'id'            => $log->id,
            'actual_output' => 400,
        ]);
    }

    #[Test]
    public function non_creator_non_admin_cannot_update_log(): void
    {
        $owner = $this->actingUser();
        $stranger = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $log = ProductionLog::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'is_validated'   => false,
            'created_by'     => $owner->id,
        ]);

        $response = $this->actingAs($stranger)->patch(route('production-logs.update', $log), [
            'actual_output' => 999,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function validated_log_cannot_be_updated_even_by_creator(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $log = ProductionLog::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'is_validated'   => true,
            'created_by'     => $user->id,
        ]);

        $response = $this->actingAs($user)->patch(route('production-logs.update', $log), [
            'actual_output' => 999,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function creator_can_validate_own_log(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $log = ProductionLog::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'is_validated'   => false,
            'created_by'     => $user->id,
        ]);

        $response = $this->actingAs($user)->patch(route('production-logs.validate', $log));

        $response->assertRedirect();
        $this->assertDatabaseHas('production_logs', [
            'id'           => $log->id,
            'is_validated' => true,
        ]);
    }

    #[Test]
    public function stranger_cannot_validate_someone_elses_log(): void
    {
        $owner = $this->actingUser();
        $stranger = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $log = ProductionLog::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'is_validated'   => false,
            'created_by'     => $owner->id,
        ]);

        $response = $this->actingAs($stranger)->patch(route('production-logs.validate', $log));

        $response->assertForbidden();
    }

    #[Test]
    public function creator_can_delete_unvalidated_log(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();

        $log = ProductionLog::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id'       => $shift->id,
            'is_validated'   => false,
            'created_by'     => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('production-logs.destroy', $log));

        $response->assertRedirect();
        $this->assertDatabaseMissing('production_logs', ['id' => $log->id]);
    }
}