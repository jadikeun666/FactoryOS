<?php

namespace Tests\Feature\Controllers;

use App\Models\ReorderAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see docs/prd.md US-12, FR-08
 * @see app/Policies/ReorderAlertPolicy.php
 *
 * Feature test untuk PATCH /mrp/alerts/{reorderAlert}/status -- item
 * kecil #1 sesi Soketi. Otorisasi dan aturan transisi berurutan
 * dikonfirmasi eksplisit dengan owner project sebelum implementasi.
 */
class ReorderAlertUpdateStatusTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    #[Test]
    public function admin_can_transition_open_to_acknowledged(): void
    {
        $admin = $this->userWithRole('admin');
        $alert = ReorderAlert::factory()->create(['status' => 'open']);

        $response = $this->actingAs($admin)->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'acknowledged']
        );

        $response->assertOk();
        $response->assertJsonPath('status', 'acknowledged');
        $this->assertSame('acknowledged', $alert->fresh()->status);
    }

    #[Test]
    public function ppic_can_transition_acknowledged_to_ordered(): void
    {
        $ppic = $this->userWithRole('ppic');
        $alert = ReorderAlert::factory()->create(['status' => 'acknowledged']);

        $response = $this->actingAs($ppic)->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'ordered']
        );

        $response->assertOk();
        $this->assertSame('ordered', $alert->fresh()->status);
    }

    #[Test]
    public function production_manager_cannot_update_status(): void
    {
        $pm = $this->userWithRole('production_manager');
        $alert = ReorderAlert::factory()->create(['status' => 'open']);

        $response = $this->actingAs($pm)->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'acknowledged']
        );

        $response->assertStatus(403);
        $this->assertSame('open', $alert->fresh()->status);
    }

    #[Test]
    public function operator_cannot_update_status(): void
    {
        $operator = $this->userWithRole('operator');
        $alert = ReorderAlert::factory()->create(['status' => 'open']);

        $response = $this->actingAs($operator)->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'acknowledged']
        );

        $response->assertStatus(403);
    }

    #[Test]
    public function guest_cannot_update_status(): void
    {
        $alert = ReorderAlert::factory()->create(['status' => 'open']);

        $response = $this->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'acknowledged']
        );

        $response->assertStatus(401);
    }

    #[Test]
    public function it_rejects_skipping_from_open_directly_to_ordered(): void
    {
        $admin = $this->userWithRole('admin');
        $alert = ReorderAlert::factory()->create(['status' => 'open']);

        $response = $this->actingAs($admin)->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'ordered']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
        $this->assertSame('open', $alert->fresh()->status);
    }

    #[Test]
    public function it_rejects_moving_backward_from_ordered_to_acknowledged(): void
    {
        $admin = $this->userWithRole('admin');
        $alert = ReorderAlert::factory()->create(['status' => 'ordered']);

        $response = $this->actingAs($admin)->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'acknowledged']
        );

        $response->assertStatus(422);
        $this->assertSame('ordered', $alert->fresh()->status);
    }

    #[Test]
    public function it_rejects_invalid_status_value(): void
    {
        $admin = $this->userWithRole('admin');
        $alert = ReorderAlert::factory()->create(['status' => 'open']);

        $response = $this->actingAs($admin)->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'closed']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    #[Test]
    public function response_includes_material_relation(): void
    {
        $admin = $this->userWithRole('admin');
        $alert = ReorderAlert::factory()->create(['status' => 'open']);

        $response = $this->actingAs($admin)->patchJson(
            route('mrp.alerts.update-status', $alert),
            ['status' => 'acknowledged']
        );

        $response->assertJsonStructure(['material' => ['id', 'name']]);
    }
}
