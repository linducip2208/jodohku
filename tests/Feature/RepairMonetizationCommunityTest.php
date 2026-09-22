<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Ad;
use App\Models\Coupon;
use App\Models\CreditProduct;
use App\Models\Event;
use App\Models\FraudRiskScore;
use App\Models\Like;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\ProfileView;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\AiChatAssistantService;
use App\Services\AiService;
use App\Services\BoostService;
use App\Services\ChatService;
use App\Services\CreditService;
use App\Services\FraudDetectionService;
use App\Services\ScamDetectionService;
use App\Services\SubscriptionService;
use App\Services\TwoFactorService;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepairMonetizationCommunityTest extends TestCase
{
    use RefreshDatabase;

    protected function event(array $over = []): Event
    {
        return Event::create(array_merge([
            'host_id' => User::factory()->create()->id,
            'title' => 'Kopi Darat Jakarta',
            'city' => 'Jakarta',
            'latitude' => -6.2000,
            'longitude' => 106.8167,
            'starts_at' => now()->addDays(3),
            'capacity' => 2,
            'status' => 'published',
        ], $over));
    }

    protected function plan(string $code = 'premium_monthly', array $over = []): MembershipPlan
    {
        return MembershipPlan::firstOrCreate(['code' => $code], array_merge([
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ], $over));
    }

    public function test_event_join_leave_capacity_and_guards(): void
    {
        $event = $this->event();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();

        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/join")->assertCreated();
        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/join")->assertStatus(422);
        $this->actingAs($b)->postJson("/api/v1/events/{$event->id}/join")->assertCreated();
        // Capacity reached.
        $this->actingAs($c)->postJson("/api/v1/events/{$event->id}/join")->assertStatus(422);
        $this->assertEquals(2, $event->members()->count());

        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/leave")->assertOk();
        $this->actingAs($c)->postJson("/api/v1/events/{$event->id}/join")->assertCreated();

        // Closed events reject joins.
        $draft = $this->event(['status' => 'draft', 'title' => 'Draft Event']);
        $this->actingAs($a)->postJson("/api/v1/events/{$draft->id}/join")->assertStatus(422);
    }

    public function test_event_rsvp_and_index_and_nearby(): void
    {
        $event = $this->event();
        $a = User::factory()->create();

        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/rsvp", ['status' => 'maybe'])
            ->assertOk()->assertJsonPath('message', 'RSVP: maybe');
        $this->assertEquals('maybe', $event->members()->where('user_id', $a->id)->first()->status);
        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/rsvp", ['status' => 'nope'])->assertStatus(422);
        // Default status works (web form posts without status).
        $b = User::factory()->create();
        $this->actingAs($b)->postJson("/api/v1/events/{$event->id}/rsvp")->assertOk();

        $this->actingAs($a)->getJson('/api/v1/events?status=ongoing')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($a)->getJson('/api/v1/events')->assertOk()->assertJsonFragment(['title' => 'Kopi Darat Jakarta']);
        $this->actingAs($a)->getJson('/api/v1/events?status=bogus')->assertStatus(422);

        $res = $this->actingAs($a)->postJson('/events/nearby', ['latitude' => -6.21, 'longitude' => 106.82, 'radius' => 25])
            ->assertOk();
        $this->assertEquals($event->id, $res->json('data.0.id'));
        $this->assertNotNull($res->json('data.0.distance_km'));

        // Far away event excluded.
        $this->event(['title' => 'Surabaya Meet', 'latitude' => -7.25, 'longitude' => 112.75]);
        $res = $this->actingAs($a)->postJson('/events/nearby', ['latitude' => -6.21, 'longitude' => 106.82, 'radius' => 25])->assertOk();
        $this->assertEquals(1, $res->json('total'));
    }

    public function test_coupon_quote_and_checkout_quote(): void
    {
        $user = User::factory()->create();
        $this->plan();
        Coupon::create([
            'code' => 'WELCOME10', 'name' => 'Welcome', 'type' => 'percent',
            'value' => 10, 'max_discount' => 20000, 'min_order' => 1000,
            'per_user_limit' => 5, 'is_active' => true,
        ]);

        $this->actingAs($user)->postJson('/api/v1/coupons/quote', ['coupon_code' => 'WELCOME10', 'subtotal' => 49000])
            ->assertOk()->assertJsonPath('discount', 4900);
        $this->actingAs($user)->postJson('/api/v1/coupons/quote', ['coupon_code' => 'NOPE', 'subtotal' => 49000])
            ->assertStatus(422);

        $this->actingAs($user)->postJson('/api/v1/checkout/quote', ['subscription_plan' => 'premium_monthly', 'coupon_code' => 'WELCOME10'])
            ->assertOk()->assertJsonPath('total', 44100);
        $this->assertEquals(0, Payment::count(), 'Quote must not persist payments.');
    }

    public function test_boost_status_and_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/boost/status')->assertOk()->assertJsonPath('live', false);

        app(BoostService::class)->activate($user, 30);
        $this->actingAs($user)->getJson('/api/v1/boost/status')->assertOk()
            ->assertJsonPath('live', true)
            ->assertJsonStructure(['ends_at', 'remaining_seconds']);
        $this->actingAs($user)->getJson('/api/v1/boosts/history')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_wallet_summary_and_transaction_filters(): void
    {
        $user = User::factory()->create();
        $svc = app(CreditService::class);
        $svc->award($user, 100, 'Bonus');
        $svc->spend($user, 30, 'Gift');

        $this->actingAs($user)->getJson('/api/v1/wallet/summary')->assertOk()
            ->assertJsonPath('balance', 70)
            ->assertJsonPath('lifetime_earned', 100)
            ->assertJsonPath('lifetime_spent', 30);

        $this->actingAs($user)->getJson('/api/v1/wallet/transactions?type=spend')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson('/api/v1/wallet/transactions?from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()->assertJsonPath('total', 2);
    }

    public function test_trial_eligibility_and_plan_switch(): void
    {
        $user = User::factory()->create();
        $planA = $this->plan('basic_monthly', ['name' => 'Basic', 'price' => 29000]);
        $planB = $this->plan('premium_monthly', ['name' => 'Premium', 'price' => 49000]);

        $this->actingAs($user)->getJson('/api/v1/subscriptions/trial-eligibility')->assertOk()->assertJsonPath('eligible', true);

        $svc = app(SubscriptionService::class);
        $svc->activate($user, $planA, ['trial_days' => 7]);
        $this->actingAs($user)->getJson('/api/v1/subscriptions/trial-eligibility')->assertOk()->assertJsonPath('eligible', false);

        $this->actingAs($user)->postJson('/api/v1/subscriptions/switch', ['plan_code' => 'premium_monthly'])
            ->assertCreated();
        $this->assertEquals($planB->id, $user->activeSubscription()->membership_plan_id);
        $this->assertTrue($user->refresh()->is_premium);
    }

    public function test_verification_status_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/verification/status')->assertOk()->assertJsonCount(0, 'requests');

        app(VerificationService::class)->submit($user, 'id_card', [], 'cek');
        $this->actingAs($user)->getJson('/api/v1/verification/status')->assertOk()
            ->assertJsonPath('requests.0.type', 'id_card')
            ->assertJsonPath('requests.0.status', 'pending');
    }

    public function test_retry_rebuilds_full_order(): void
    {
        Http::fake(['*' => Http::response(['payment_url' => 'https://pay.test/x'], 200)]);
        $user = User::factory()->create();
        $this->plan();
        CreditProduct::create(['code' => 'c100', 'name' => 'C100', 'credits' => 100, 'bonus_credits' => 0, 'price' => 15000, 'is_active' => true]);
        Coupon::create(['code' => 'FIX5K', 'name' => 'Fix', 'type' => 'fixed', 'value' => 5000, 'is_active' => true]);

        $payment = Payment::create([
            'user_id' => $user->id, 'gateway' => 'midtrans', 'amount' => 64000,
            'total_amount' => 59000, 'currency' => 'IDR', 'status' => PaymentStatus::Failed,
        ]);
        $plan = MembershipPlan::where('code', 'premium_monthly')->first();
        $product = CreditProduct::where('code', 'c100')->first();
        $coupon = Coupon::where('code', 'FIX5K')->first();
        $payment->items()->createMany([
            ['item_type' => 'subscription', 'item_id' => $plan->id, 'name' => $plan->name, 'quantity' => 1, 'unit_price' => 49000, 'subtotal' => 49000],
            ['item_type' => 'credits', 'item_id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'unit_price' => 15000, 'subtotal' => 15000],
            ['item_type' => 'discount', 'item_id' => $coupon->id, 'name' => 'Coupon FIX5K', 'quantity' => 1, 'unit_price' => -5000, 'subtotal' => -5000],
        ]);

        $this->actingAs($user)->postJson("/api/v1/payments/{$payment->id}/retry")->assertCreated();
        $retry = Payment::latest('id')->first();
        $this->assertNotEquals($payment->id, $retry->id);
        $this->assertTrue($retry->items()->where('item_type', 'credits')->exists());
        $this->assertTrue($retry->items()->where('item_type', 'discount')->exists());
        $this->assertEquals(59000, (float) $retry->total_amount);
    }

    public function test_ads_serve_only_when_servable(): void
    {
        $user = User::factory()->create();
        $draft = Ad::create(['title' => 'D', 'placement' => 'feed', 'status' => AdStatus::Draft]);
        $live = Ad::create(['title' => 'L', 'placement' => 'feed', 'status' => AdStatus::Active]);

        $this->actingAs($user)->postJson("/api/v1/ads/{$draft->id}/impression")->assertOk();
        $this->assertEquals(0, $draft->refresh()->impressions_count);
        $this->actingAs($user)->postJson("/api/v1/ads/{$live->id}/impression")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/ads/{$live->id}/click")->assertOk();
        $this->assertEquals(1, $live->refresh()->impressions_count);
        $this->assertEquals(1, $live->refresh()->clicks_count);

        $this->actingAs($user)->getJson('/api/v1/ads')->assertOk()->assertJsonCount(1);
        $this->actingAs($user)->getJson('/api/v1/ads/stats')->assertOk()->assertJsonPath('0.clicks', 1);
    }

    public function test_admin_analytics_accuracy(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        ProfileView::create(['profile_user_id' => $b->id, 'viewer_id' => $a->id]);
        ProfileView::create(['profile_user_id' => $b->id, 'viewer_id' => User::factory()->create()->id]);
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()]);
        Like::create(['liker_id' => $a->id, 'liked_id' => $b->id]);
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);
        app(ChatService::class)->sendMessage($conv, $a, ['body' => 'halo']);

        $this->actingAs($admin)->getJson('/admin/analytics/top-users')->assertOk()
            ->assertJsonPath('most_profile_viewed.0.user_id', $b->id)
            ->assertJsonPath('most_profile_viewed.0.count', 2);

        $this->actingAs($admin)->getJson('/admin/analytics/cohorts')->assertOk()
            ->assertJsonPath('0.with_match', 2);
        $eng = $this->actingAs($admin)->getJson('/admin/analytics/engagement?days=1')->assertOk();
        // DAU counts message senders + likers + users registered that day (4 users created today).
        $this->assertGreaterThanOrEqual(4, $eng->json('1.dau'));
        $this->assertEquals(1, $eng->json('1.likes'));
        $this->assertEquals(1, $eng->json('1.messages'));
    }

    public function test_scam_detection_rules(): void
    {
        $svc = app(ScamDetectionService::class);
        $plain = $svc->analyze('Halo, umurku 25 tahun, hobiku membaca buku dan olahraga lari.');
        $this->assertEquals([], $plain['flags']);

        $phone = $svc->analyze('hubungi aku di 081234567890 ya');
        $this->assertContains('phone_number', $phone['flags']);

        // Multiple distinct lures all count (no early break).
        $multi = $svc->analyze('investasi crypto profit harian, kirim password dan otp kamu sekarang juga, hubungi 081234567890');
        $this->assertGreaterThanOrEqual(3, count($multi['flags']));
        $this->assertGreaterThan($phone['score'], $multi['score']);
    }

    public function test_two_factor_verify_attempt_order(): void
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);
        $svc = app(TwoFactorService::class);
        $svc->sendChallenge($user);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($svc->verify($user, '000000'));
        }
        $this->expectException(\RuntimeException::class);
        $svc->verify($user, '000000');
    }

    public function test_icebreakers_padded_to_count(): void
    {
        $me = User::factory()->create();
        $cand = User::factory()->create(['display_name' => 'Sinta']);
        $mock = $this->mock(AiService::class);
        $mock->shouldReceive('chat')->once()->andReturn(['text' => 'Halo Sinta!']);
        $svc = app(AiChatAssistantService::class);

        $items = $svc->icebreakers($me, $cand, 3);
        $this->assertCount(3, $items);
        $this->assertEquals('Halo Sinta!', $items[0]);
    }

    public function test_fraud_device_lookup_matches_storage(): void
    {
        $svc = app(FraudDetectionService::class);
        $ua = str_repeat('Mozilla/5.0-test-agent-xyz ', 40); // > 500 chars
        $a = User::factory()->create();
        $b = User::factory()->create();

        $svc->scoreUser($a, ['device' => $ua, 'ip' => '10.0.0.1']);
        $stored = FraudRiskScore::where('user_id', $a->id)->first()->device_fingerprint;
        $this->assertEquals(500, mb_strlen($stored));

        $result = $svc->scoreUser($b, ['device' => $ua, 'ip' => '10.0.0.2']);
        $this->assertArrayHasKey('device_reuse', $result['signals']);
    }
}
