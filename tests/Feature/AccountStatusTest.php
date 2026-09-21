<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_banned_user_cannot_login_web_or_api(): void
    {
        $u = User::factory()->create(['password' => bcrypt('password123'), 'status' => UserStatus::Banned]);

        $this->post('/login', ['email' => $u->email, 'password' => 'password123'])
            ->assertRedirect();
        $this->assertGuest();

        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'password123'])
            ->assertForbidden();
    }

    public function test_suspended_token_is_rejected_on_protected_routes(): void
    {
        $u = User::factory()->create(['status' => UserStatus::Suspended]);
        $token = $u->createToken('api')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
        $this->actingAs($u)->get('/home')->assertRedirect(route('login'));
    }

    public function test_pending_verification_user_can_login(): void
    {
        $u = User::factory()->create(['password' => bcrypt('password123'), 'status' => UserStatus::PendingVerification]);
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'password123'])
            ->assertOk()->assertJsonStructure(['token']);
    }
}
