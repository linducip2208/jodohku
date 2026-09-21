<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_admin_overview_but_admin_can(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/admin/overview')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonStructure(['users_total', 'reports_pending']);
    }

    public function test_guest_cannot_access_admin(): void
    {
        $this->getJson('/api/v1/admin/overview')->assertUnauthorized();
    }
}
