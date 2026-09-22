<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function user(string $role): User
    {
        return User::factory()->create(['role' => UserRole::from($role)]);
    }

    public function test_member_cannot_touch_admin(): void
    {
        $m = $this->user('member');
        $this->actingAs($m)->get('/admin')->assertForbidden();
        $this->actingAs($m)->get('/admin/users')->assertForbidden();
        $this->actingAs($m)->get('/admin/membership/payments')->assertForbidden();
        $this->actingAs($m, 'sanctum')->getJson('/api/v1/admin/overview')->assertForbidden();
    }

    public function test_moderator_scoped_to_trust_and_safety(): void
    {
        $mod = $this->user('moderator');
        // Allowed: users read, moderation queue, photos queue, community.
        $this->actingAs($mod)->get('/admin/users')->assertOk();
        $this->actingAs($mod)->get('/admin/moderation')->assertOk();
        $this->actingAs($mod)->get('/admin/photos/queue')->assertOk();
        // Money & system: forbidden.
        $this->actingAs($mod)->get('/admin/membership/payments')->assertForbidden();
        $this->actingAs($mod)->get('/admin/membership/gateways')->assertForbidden();
        $this->actingAs($mod)->get('/admin/settings')->assertForbidden();
        $this->actingAs($mod)->get('/admin/feature-flags')->assertForbidden();
        $this->actingAs($mod)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_operator_scoped_to_live_chat(): void
    {
        $op = $this->user('operator');
        $this->actingAs($op)->get('/admin/operators/queue')->assertOk();
        $this->actingAs($op)->get('/admin/chat/virtual')->assertOk();
        $this->actingAs($op)->get('/admin/users')->assertForbidden();
        $this->actingAs($op)->get('/admin/membership/payments')->assertForbidden();
        $this->actingAs($op)->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_full_but_not_secrets_without_superadmin(): void
    {
        $admin = $this->user('admin');
        PaymentGateway::firstOrCreate(['code' => 'midtrans'], ['name' => 'Midtrans']);
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/membership/payments')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->get('/admin/feature-flags')->assertForbidden();
        $this->actingAs($admin)->post('/admin/gateways/midtrans/credentials', [])->assertForbidden();
    }

    public function test_superadmin_can_manage_flags_and_only_superadmin_deletes_users(): void
    {
        $super = $this->user('superadmin');
        $admin = $this->user('admin');
        $member = $this->user('member');
        $this->actingAs($super)->get('/admin/feature-flags')->assertOk();
        // Policy-level: only superadmin may delete users.
        $this->assertTrue($super->can('delete', $member));
        $this->assertFalse($admin->can('delete', $member));
    }
}
