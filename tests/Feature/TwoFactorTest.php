<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_requires_code_then_issues_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        /** @var TwoFactorService $tfa */
        $tfa = app(TwoFactorService::class);
        $tfa->enable($user);
        $this->assertTrue($user->fresh()->two_factor_enabled);

        // Password-only login returns challenge, no token.
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password123',
        ])->assertOk();
        $this->assertTrue($login->json('two_factor_required'));
        $this->assertArrayNotHasKey('token', $login->json());

        // Wrong code rejected.
        $this->postJson('/api/v1/auth/2fa/verify', [
            'user_id' => $user->id, 'code' => '000000',
        ])->assertStatus(422);

        // Extract the real code from the mail fake via cache bypass:
        // re-send is throttled, so resolve by reading notification.
        $code = null;
        Notification::assertSentTo($user, TwoFactorCode::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->assertNotNull($code);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'user_id' => $user->id, 'code' => $code,
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_web_login_redirects_to_challenge(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        app(TwoFactorService::class)->enable($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('2fa.challenge'));
        $this->assertGuest();
        $this->get('/2fa')->assertOk();
    }
}
