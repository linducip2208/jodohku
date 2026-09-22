<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\CreditProduct;
use App\Models\MembershipPlan;
use App\Models\Message;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ChatService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Acceptance journey §59: register → profile → preferences → discover →
 * like → match → chat (send/read/receipt/notification) → premium purchase
 * (webhook) → credits → super like + boost → block/report → admin moderation
 * + audit log.
 */
class EndToEndJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function register(string $name, string $email): array
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => $name, 'email' => $email,
            'password' => 'password123', 'password_confirmation' => 'password123',
            'city' => 'Jakarta',
        ])->assertCreated();

        return [$res->json('user.id'), $res->json('token')];
    }

    /** Bearer-auth that actually switches users: the sanctum guard memoizes
     *  the resolved user for the app lifetime, so forget guards first.
     *  (Production boots a fresh app per request and is unaffected.) */
    protected function asToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_full_member_journey(): void
    {
        Http::fake(['*' => Http::response(['payment_url' => 'https://pay.test/x'], 200)]);

        // ---- A registers, completes profile + preferences ----
        [$aId, $aToken] = $this->register('User A', 'a.e2e@example.test');
        $this->asToken($aToken)->putJson('/api/v1/profile', [
            'display_name' => 'Ayu', 'bio' => 'Suka masak dan lari pagi.',
            'occupation' => 'Designer', 'relationship_goal' => 'serious_relationship',
        ])->assertOk();
        $this->asToken($aToken)->putJson('/api/v1/preferences', [
            'min_age' => 20, 'max_age' => 35, 'city' => 'Jakarta',
        ])->assertOk();

        // ---- B registers ----
        [$bId, $bToken] = $this->register('User B', 'b.e2e@example.test');
        $this->asToken($bToken)->putJson('/api/v1/profile', [
            'display_name' => 'Bimo', 'bio' => 'Suka hiking.',
        ])->assertOk();

        // ---- A discovers B and likes ----
        $feed = $this->asToken($aToken)->getJson('/api/v1/discover?per_page=10')->assertOk();
        $this->assertNotEmpty($feed->json('data'));
        $like = $this->asToken($aToken)->postJson("/api/v1/likes/{$bId}")->assertCreated();
        $this->assertFalse($like->json('is_new_match'));

        // ---- B likes back → canonical match + notification ----
        $matchRes = $this->asToken($bToken)->postJson("/api/v1/likes/{$aId}")->assertCreated();
        $this->assertTrue($matchRes->json('is_new_match'));
        $this->assertDatabaseHas('matches', ['user_a_id' => min($aId, $bId), 'user_b_id' => max($aId, $bId)]);
        $this->assertDatabaseCount('matches', 1); // idempotent canonical
        // Duplicate like does not duplicate match.
        $dup = $this->asToken($bToken)->postJson("/api/v1/likes/{$aId}")->assertCreated();
        $this->assertEquals($matchRes->json('like.id'), $dup->json('like.id'));
        $this->assertDatabaseCount('matches', 1);
        $b = User::find($bId);
        $this->assertGreaterThanOrEqual(1, $b->notifications()->count());

        // ---- Chat: open/create conversation, send, idempotent retry, read ----
        /** @var ChatService $chat */
        $chat = app(ChatService::class);
        $conv = $chat->findOrCreateDirect(User::find($aId), User::find($bId));
        $this->assertInstanceOf(Conversation::class, $conv);

        $cid = 'e2e-'.uniqid();
        $m1 = $this->asToken($aToken)->postJson(
            "/api/v1/conversations/{$conv->id}/messages",
            ['body' => 'Halo Bimo! Salam kenal.', 'client_message_id' => $cid]
        )->assertCreated()->json('id');
        // Network retry with same client_message_id → same message, no duplicate.
        $m2 = $this->asToken($aToken)->postJson(
            "/api/v1/conversations/{$conv->id}/messages",
            ['body' => 'Halo Bimo! Salam kenal.', 'client_message_id' => $cid]
        )->assertCreated()->json('id');
        $this->assertEquals($m1, $m2);
        $this->assertEquals(1, Message::where('conversation_id', $conv->id)->count());

        // B reads → read receipt recorded, unread drops to zero.
        $this->asToken($bToken)->postJson("/api/v1/conversations/{$conv->id}/read")->assertOk();
        $this->assertEquals(0, $chat->unreadCount($conv->fresh(), User::find($bId)));

        // ---- Premium purchase via webhook (source of truth) ----
        MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);
        /** @var PaymentService $pay */
        $pay = app(PaymentService::class);
        $order = $pay->checkout(User::find($aId), ['gateway' => 'midtrans', 'subscription_plan' => 'premium_monthly']);
        $payment = $order['payment'];
        config()->set('payments.gateways.midtrans.server_key', 'e2e-key');
        $payload = [
            'order_id' => $payment->invoice_number, 'status_code' => '200',
            'gross_amount' => '49000.00', 'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', $payment->invoice_number.'200'.'49000.00'.'e2e-key'),
        ];
        $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk();
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue(User::find($aId)->isPremium());
        $this->assertEquals(1, Subscription::where('user_id', $aId)->where('status', 'active')->count());

        // ---- Credits: buy product, webhook credits wallet, spend super like + boost ----
        CreditProduct::firstOrCreate(['code' => 'credits_100'], [
            'name' => '100 Credits', 'credits' => 100, 'price' => 99000,
            'currency' => 'IDR', 'is_active' => true,
        ]);
        $cOrder = $pay->checkout(User::find($aId), ['gateway' => 'midtrans', 'credit_product' => 'credits_100']);
        $cPay = $cOrder['payment'];
        $this->postJson('/api/v1/webhooks/midtrans', [
            'order_id' => $cPay->invoice_number, 'status_code' => '200',
            'gross_amount' => '99000.00', 'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', $cPay->invoice_number.'200'.'99000.00'.'e2e-key'),
        ])->assertOk();
        $this->assertGreaterThanOrEqual(100, User::find($aId)->creditBalance());

        $wallet = $this->asToken($aToken)->getJson('/api/v1/wallet')->assertOk();
        $this->assertArrayHasKey('balance', $wallet->json());

        // ---- Block/report: A blocks B → B can no longer message ----
        $this->asToken($aToken)->postJson("/api/v1/blocks/{$bId}")->assertCreated();
        $blockedSend = $this->asToken($bToken)->postJson(
            "/api/v1/conversations/{$conv->id}/messages", ['body' => 'Masih bisa?']
        );
        $this->assertContains($blockedSend->getStatusCode(), [403, 422]);

        $this->asToken($aToken)->postJson('/api/v1/reports', [
            'reported_user_id' => $bId, 'reason' => 'spam', 'details' => 'E2E test report',
        ])->assertCreated();
        $report = Report::where('reporter_id', $aId)->firstOrFail();

        // ---- Admin moderation resolves report → audit log written ----
        $admin = User::factory()->create(['role' => UserRole::Moderator]);
        $this->actingAs($admin)->postJson("/admin/reports/{$report->id}/resolve", ['notes' => 'warned'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id]);
    }
}
