<?php

namespace Tests\Feature\Controllers;

use App\Models\OeeSnapshot;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see docs/architecture.md § Controllers (OeeController)
 * @see docs/oee-formulas.md
 *
 * Feature test untuk seluruh endpoint OeeController -- sebelumnya belum
 * ada test sama sekali meski logic-nya (OeeCalculatorService,
 * DowntimeAnalysisService) sudah lama final & teruji lewat unit test.
 * Test ini murni memverifikasi controller-nya (routing, validasi, auth,
 * response shape), bukan kalkulasinya.
 */
class OeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    #[Test]
    public function dashboard_renders_with_default_work_center_when_none_specified(): void
    {
        $user = $this->actingUser();
        WorkCenter::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('oee.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('OEE/Dashboard')
            ->has('workCenters')
            ->has('selectedWorkCenterId')
        );
    }

    #[Test]
    public function dashboard_respects_work_center_id_query_param(): void
    {
        $user = $this->actingUser();
        WorkCenter::factory()->create(['is_active' => true]);
        $target = WorkCenter::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)
            ->get(route('oee.dashboard', ['work_center_id' => $target->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('selectedWorkCenterId', (string) $target->id)
        );
    }

    #[Test]
    public function dashboard_rejects_nonexistent_work_center_id(): void
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)
            ->get(route('oee.dashboard', ['work_center_id' => 99999]));

        $response->assertSessionHasErrors('work_center_id');
    }

    #[Test]
    public function guest_cannot_access_dashboard(): void
    {
        $response = $this->get(route('oee.dashboard'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function pareto_endpoint_returns_json_for_valid_date_range(): void
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)->getJson(route('oee.pareto', [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }

    #[Test]
    public function pareto_endpoint_requires_date_to_after_or_equal_date_from(): void
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)->getJson(route('oee.pareto', [
            'date_from' => now()->toDateString(),
            'date_to' => now()->subDays(7)->toDateString(),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('date_to');
    }

    #[Test]
    public function trend_endpoint_requires_work_center_id(): void
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)->getJson(route('oee.trend', [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('work_center_id');
    }

    #[Test]
    public function trend_endpoint_returns_data_for_valid_work_center(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();

        $response = $this->actingAs($user)->getJson(route('oee.trend', [
            'work_center_id' => $wc->id,
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk();
    }

    #[Test]
    public function benchmark_endpoint_returns_gap_structure_for_snapshot(): void
    {
        $user = $this->actingUser();
        $snapshot = OeeSnapshot::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('oee.benchmark', $snapshot));

        $response->assertOk();
        $response->assertJsonStructure([
            'oee' => ['actual', 'world_class', 'gap'],
            'availability' => ['actual', 'world_class', 'gap'],
            'performance' => ['actual', 'world_class', 'gap'],
            'quality' => ['actual', 'world_class', 'gap'],
        ]);
    }

    #[Test]
    public function latest_snapshot_endpoint_returns_null_when_no_snapshot_exists(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('oee.latest-snapshot', $wc));

        $response->assertOk();
        $response->assertJson(['snapshot' => null, 'benchmark' => null]);
    }

    #[Test]
    public function latest_snapshot_endpoint_returns_snapshot_and_benchmark_when_exists(): void
    {
        $user = $this->actingUser();
        $wc = WorkCenter::factory()->create();
        $shift = Shift::factory()->create();
        OeeSnapshot::factory()->create([
            'work_center_id' => $wc->id,
            'shift_id' => $shift->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('oee.latest-snapshot', $wc));

        $response->assertOk();
        $response->assertJsonStructure(['snapshot', 'benchmark']);
        $response->assertJsonPath('snapshot.work_center_id', $wc->id);
    }
}
