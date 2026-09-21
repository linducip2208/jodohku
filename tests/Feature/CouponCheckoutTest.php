<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CouponCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_reduces_total_and_records_redemption(): void
    {
        Http::fake(['*' => Http::response(['payment_url' => 'https://pay.test/x'], 200)]);

        $user = User::factory()->create();
        MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);
        Coupon::create([
            'code' => 'WELCOME10', 'name' => 'Welcome', 'type' => 'percent',
            'value' => 10, 'max_discount' => 20000, 'min_order' => 29000,
            'per_user_limit' => 1, 'is_active' => true,
        ]);

        /** @var PaymentService $svc */
        $svc = app(PaymentService::class);
        $result = $svc->checkout($user, [
            'gateway' => 'midtrans',
            'subscription_plan' => 'premium_monthly',
            'coupon_code' => 'welcome10', // lowercase still works
        ]);

        $payment = $result['payment'];
        $this->assertEquals(49000, (float) $payment->amount);
        $this->assertEquals(44100, (float) $payment->total_amount);
        $this->assertTrue($payment->items()->where('item_type', 'discount')->exists());
        $this->assertEquals(1, CouponRedemption::where('payment_id', $payment->id)->count());
        $this->assertEquals(1, (int) Coupon::where('code', 'WELCOME10')->first()->used_count);

        // Second use by same user exceeds per_user_limit.
        $this->expectException(\InvalidArgumentException::class);
        $svc->checkout($user, [
            'gateway' => 'midtrans',
            'subscription_plan' => 'premium_monthly',
            'coupon_code' => 'WELCOME10',
        ]);
    }

    public function test_expired_coupon_rejected_without_orphan_payment(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $user = User::factory()->create();
        MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);
        Coupon::create([
            'code' => 'OLD', 'name' => 'Old', 'type' => 'fixed',
            'value' => 5000, 'is_active' => true, 'ends_at' => now()->subDay(),
        ]);

        $countBefore = \App\Models\Payment::count();
        try {
            app(PaymentService::class)->checkout($user, [
                'gateway' => 'midtrans',
                'subscription_plan' => 'premium_monthly',
                'coupon_code' => 'OLD',
            ]);
            $this->fail('Expired coupon should throw.');
        } catch (\InvalidArgumentException) {
            $this->assertEquals($countBefore, \App\Models\Payment::count());
        }
    }
}
