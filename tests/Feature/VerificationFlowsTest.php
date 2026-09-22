<?php

namespace Tests\Feature;

use App\Models\FraudEvent;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use App\Services\FraudDetectionService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_signed_link_verifies(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->assertFalse($user->hasVerifiedEmail());

        $this->actingAs($user)->post('/verify-email/send')->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);

        $url = URL::temporarySignedRoute(
            'verification.verify', now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Tampered hash rejected.
        $evil = User::factory()->create(['email_verified_at' => null]);
        $bad = URL::temporarySignedRoute(
            'verification.verify', now()->addMinutes(60),
            ['id' => $evil->id, 'hash' => sha1('other@example.test')]
        );
        $this->actingAs($evil)->get($bad)->assertForbidden();
        $this->assertFalse($evil->fresh()->hasVerifiedEmail());
    }

    public function test_password_reset_broker_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->assertNotEmpty($token);

        // Wrong token rejected, password unchanged.
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => 'wrong-token',
            'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors('email');

        // Correct token rotates password.
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('login'));

        // Old password dead, new password works.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->flushSession();
        $this->post('/login', ['email' => $user->email, 'password' => 'newpassword123'])->assertRedirect('/home');
    }

    public function test_phone_otp_verify_and_reject(): void
    {
        $user = User::factory()->create(['phone_verified_at' => null]);
        $this->actingAs($user)->post('/phone-verify/send', ['phone' => '081234567890'])->assertRedirect();

        $this->actingAs($user)->post('/phone-verify', ['phone' => '081234567890', 'otp' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->phone_verified_at);

        $real = Cache::get('phone-otp:081234567890');
        $this->assertNotNull($real);
        $this->actingAs($user)->post('/phone-verify', ['phone' => '081234567890', 'otp' => $real])
            ->assertRedirect('/home');
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_fraud_scoring_levels_and_evidence(): void
    {
        /** @var FraudDetectionService $fraud */
        $fraud = app(FraudDetectionService::class);

        $clean = User::factory()->create();
        $clean->profile()->updateOrCreate([], ['bio' => 'Lengkap dan sopan.']);
        $low = $fraud->scoreUser($clean->fresh(), ['ip' => '10.0.0.1', 'device' => 'dev-clean-1']);
        $this->assertEquals('low', $low['level']);
        $this->assertDatabaseMissing('fraud_events', ['user_id' => $clean->id]);

        // Spammer: brand-new account blasting messages from shared IP.
        $spam = User::factory()->create();
        for ($i = 0; $i < 6; $i++) {
            User::factory()->create();
            $fraud->scoreUser(User::latest('id')->first(), ['ip' => '10.9.9.9', 'device' => 'dev-shared']);
        }
        $peer = User::factory()->create();
        $conv = app(ChatService::class)->findOrCreateDirect($spam, $peer);
        foreach (range(1, 60) as $i) {
            Message::create([
                'conversation_id' => $conv->id, 'sender_id' => $spam->id,
                'body' => 'spam '.$i, 'status' => 'sent', 'client_message_id' => 'fraud-'.$i,
            ]);
        }
        $result = $fraud->scoreUser($spam->fresh(), ['ip' => '10.9.9.9', 'device' => 'dev-shared']);
        $this->assertContains($result['level'], ['medium', 'high']);
        $this->assertDatabaseHas('fraud_events', ['user_id' => $spam->id]);
        $event = FraudEvent::where('user_id', $spam->id)->firstOrFail();
        $this->assertNotEmpty($event->metadata);
    }
}
