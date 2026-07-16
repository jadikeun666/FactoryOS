<?php

namespace Tests\Unit\Policies;

use PHPUnit\Framework\Attributes\Test;

use App\Models\User;
use App\Models\WorkCenter;
use App\Policies\WorkCenterPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCenterPolicyTest extends TestCase
{
    use RefreshDatabase;

    private WorkCenterPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new WorkCenterPolicy();
    }

    #[Test]
    public function any_authenticated_user_can_view_work_center(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $workCenter = WorkCenter::factory()->create();

        $this->assertTrue($this->policy->view($user, $workCenter));
    }

    #[Test]
    public function only_admin_can_update_work_center(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ppic = User::factory()->create(['role' => 'ppic']);
        $workCenter = WorkCenter::factory()->create();

        $this->assertTrue($this->policy->update($admin, $workCenter));
        $this->assertFalse($this->policy->update($ppic, $workCenter));
    }

    #[Test]
    public function only_admin_can_delete_work_center(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $workCenter = WorkCenter::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $workCenter));
        $this->assertFalse($this->policy->delete($operator, $workCenter));
    }
}