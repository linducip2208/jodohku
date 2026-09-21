<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Ad;
use App\Models\Coupon;
use App\Models\Gift;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\BoostService;
use App\Services\CreditService;
use App\Services\DiscoveryService;
use App\Services\GiftService;
use App\Services\SubscriptionService;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleEdgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_gift_atomic_no_negative(): void
    {
        Gift::firstOrCreate(['code' => 'rose'], ['name' => 'Mawar', 'credit_price' => 10, 'is_active' => true]);
        $a = User::factory()->create();
        $b = User::factory()->create();

        try {
            app(GiftService::class)->send($a, $b, 'rose');
            $this->fail('Should throw on empty wallet.');
        } catch (\RuntimeException) {
        }
        $this->assertEquals(0, $a->fresh()->creditBalance());
        $this->assertDatabaseCount('gift_transactions', 0);

        app(CreditService::class)->award($a, 10, 'test');
        app(GiftService::class)->send($a, $b, 'rose');
        $this->assertEquals(0, $a->fresh()->creditBalance());
        $this->assertDatabaseCount('gift_transactions', 1);
    }

    public function test_boost_orders_first_without_changing_score(): void
    {
        $viewer = User::factory()->create(['city' => 'Jakarta']);
        $plain = User::factory()->create(['city' => 'Jakarta']);
        $boosted = User::factory()->create(['city' => 'Jakarta']);

        /** @var BoostService $boost */
        $boost = app(BoostService::class);
        $before = app(\App\Services\MatchingEngine::class)->scorePair($viewer, $boosted)['mutual'];
        $boost->activate($boosted, 60);
        $after = app(\App\Services\MatchingEngine::class)->scorePair($viewer, $boosted)['mutual'];
        $this->assertEquals($before, $after); // score untouched

        $ids = app(DiscoveryService::class)->discover($viewer, [], 50)->pluck('id')->all();
        $this->assertLessThanOrEqual(array_search($plain->id, $ids), array_search($boosted->id, $ids));

        // Expiry works.
        $this->assertTrue($boost->isLive($boosted));
        \App\Models\Boost::where('user_id', $boosted->id)->update(['ends_at' => now()->subMinute()]);
        $this->assertEquals(1, $boost->expireDue());
        $this->assertFalse($boost->isLive($boosted->fresh()));
    }

    public function test_subscription_expiry_removes_premium(): void
    {
        $user = User::factory()->create();
        $plan = MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);
        /** @var SubscriptionService $subs */
        $subs = app(SubscriptionService::class);
        $subs->activate($user, $plan);
        $this->assertTrue($user->fresh()->isPremium());

        \App\Models\Subscription::where('user_id', $user->id)->update(['ends_at' => now()->subMinute()]);
        $this->assertGreaterThanOrEqual(1, $subs->expireDue());
        $this->assertFalse($user->fresh()->isPremium());
    }

    public function test_verification_expiry(): void
    {
        $user = User::factory()->create();
        $req = VerificationRequest::create([
            'user_id' => $user->id, 'type' => 'id_card',
            'status' => 'pending', 'expires_at' => now()->subHour(),
        ]);
        $this->assertEquals(1, app(VerificationService::class)->expireDue());
        $this->assertEquals('expired', $req->fresh()->status->value ?? $req->fresh()->status);
    }

    public function test_coupon_global_usage_limit(): void
    {
        Coupon::create([
            'code' => 'ONCE', 'name' => 'Once', 'type' => 'fixed', 'value' => 5000,
            'usage_limit' => 1, 'per_user_limit' => 5, 'is_active' => true,
        ]);
        $svc = app(\App\Services\CouponService::class);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->assertEquals(5000, $svc->quote($u1, 'ONCE', 50000)['discount']);
        // Simulate first redemption consumed the single slot.
        Coupon::where('code', 'ONCE')->increment('used_count');
        try {
            $svc->quote($u2, 'ONCE', 50000);
            $this->fail('Exhausted coupon should throw.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    public function test_chat_request_daily_cap(): void
    {
        config()->set('chat.rate_limits.requests_per_day', 1);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->actingAs($a)->postJson("/api/v1/chat-requests/{$b->id}", [])->assertCreated();
        $this->actingAs($a)->postJson("/api/v1/chat-requests/{$c->id}", [])->assertStatus(429);
    }

    public function test_ads_serve_only_in_window(): void
    {
        $live = Ad::create(['title' => 'Live', 'placement' => 'feed', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'status' => \App\Enums\AdStatus::Active]);
        Ad::create(['title' => 'Old', 'placement' => 'feed', 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDay(), 'status' => \App\Enums\AdStatus::Active]);
        $served = app(\App\Services\AdService::class)->servable('feed', 10)->pluck('id')->all();
        $this->assertContains($live->id, $served);
        $this->assertCount(1, $served);
    }
}
