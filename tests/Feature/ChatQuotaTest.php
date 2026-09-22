<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::clearCache();
    }

    protected function pair(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);

        return [$a, $b, $conv];
    }

    protected function send(User $sender, int $convId, string $body = 'halo apa kabar')
    {
        return $this->actingAs($sender)->postJson("/api/v1/conversations/{$convId}/messages", ['body' => $body]);
    }

    public function test_free_user_limited_to_one_message_per_peer(): void
    {
        [$a, $b, $conv] = $this->pair();

        $this->send($a, $conv->id)->assertCreated();
        $this->send($a, $conv->id, 'kedua')->assertStatus(429)->assertJsonPath('upgrade', true);

        // Quota endpoint reflects usage.
        $this->actingAs($a)->getJson("/api/v1/chat/quota?user_id={$b->id}")->assertOk()
            ->assertJsonPath('limit', 1)
            ->assertJsonPath('used', 1)
            ->assertJsonPath('remaining', 0);

        // A different peer has a fresh quota.
        $c = User::factory()->create();
        $conv2 = app(ChatService::class)->findOrCreateDirect($a, $c);
        $this->send($a, $conv2->id)->assertCreated();
    }

    public function test_premium_user_gets_configured_messages_per_peer(): void
    {
        // Default premium quota is 30 (route throttle is 30/min, so verify the
        // default via service and exercise HTTP mapping with a small override).
        $a = User::factory()->premium()->create();
        $b = User::factory()->create();
        $this->assertEquals(30, app(ChatService::class)->messageLimitFor($a));

        Setting::set('premium_messages_per_peer', 3, 'chat', 'integer');
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);
        for ($i = 0; $i < 3; $i++) {
            $this->send($a, $conv->id, "pesan {$i}")->assertCreated();
        }
        $this->send($a, $conv->id, 'kelebihan')->assertStatus(429)->assertJsonPath('upgrade', false);
    }

    public function test_admin_settings_override_quota(): void
    {
        Setting::set('free_messages_per_peer', 3, 'chat', 'integer');
        [$a, $b, $conv] = $this->pair();

        $this->send($a, $conv->id, 'satu')->assertCreated();
        $this->send($a, $conv->id, 'dua')->assertCreated();
        $this->send($a, $conv->id, 'tiga')->assertCreated();
        $this->send($a, $conv->id, 'empat')->assertStatus(429);

        $this->actingAs($a)->getJson("/api/v1/chat/quota?user_id={$b->id}")->assertOk()
            ->assertJsonPath('limit', 3);
    }

    public function test_staff_and_synthetic_senders_exempt(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Moderator, 'account_type' => AccountType::Moderator]);
        $b = User::factory()->create();
        $conv = app(ChatService::class)->findOrCreateDirect($staff, $b);
        $this->send($staff, $conv->id, 'satu')->assertCreated();
        $this->send($staff, $conv->id, 'dua')->assertCreated();

        $virtual = User::factory()->create(['account_type' => AccountType::Virtual]);
        $conv2 = app(ChatService::class)->findOrCreateDirect($virtual, $b);
        $this->send($virtual, $conv2->id, 'satu')->assertCreated();
        $this->send($virtual, $conv2->id, 'dua')->assertCreated();
    }

    public function test_idempotent_retry_does_not_consume_quota(): void
    {
        [$a, $b, $conv] = $this->pair();

        $payload = ['body' => 'sekali saja', 'client_message_id' => 'quota-test-1'];
        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/messages", $payload)->assertCreated();
        $this->actingAs($a)->postJson("/api/v1/conversations/{$conv->id}/messages", $payload)->assertCreated();

        $this->assertEquals(1, app(ChatService::class)->sentToPeerCount($a->id, $b->id));
    }
}
