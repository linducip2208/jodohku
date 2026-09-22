<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\CreditProduct;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\CreditService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    protected function paidSubscriptionPayment(User $user): Payment
    {
        $plan = MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);
        /** @var PaymentService $svc */
        $svc = app(PaymentService::class);
        $payment = $svc->checkout($user, ['gateway' => 'midtrans', 'subscription_plan' => 'premium_monthly'])['payment'];
        $svc->fulfill($payment->fresh());

        return $payment->fresh();
    }

    public function test_unsigned_webhook_rejected(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id, 'gateway' => 'midtrans',
            'amount' => 49000, 'total_amount' => 49000, 'currency' => 'IDR',
            'status' => PaymentStatus::Pending,
        ]);

        // No signature at all → must NOT mark paid (fail closed).
        $this->postJson('/api/v1/webhooks/midtrans', [
            'order_id' => $payment->invoice_number, 'status_code' => '200',
            'gross_amount' => '49000.00', 'transaction_status' => 'settlement',
        ])->assertStatus(400);
        $this->assertEquals(PaymentStatus::Pending, $payment->fresh()->status);
    }

    /** Single URL-routed fake (Http::fake calls merge; first '*' wins). */
    protected function fakeGateway(string $refundBody = 'rf-1', int $refundStatus = 200): void
    {
        Http::fake(function ($request) use ($refundBody, $refundStatus) {
            if (str_contains($request->url(), '/refund')) {
                return Http::response(['refund_id' => $refundBody], $refundStatus);
            }

            return Http::response(['payment_url' => 'https://pay.test/x'], 200);
        });
    }

    public function test_refund_revokes_subscription_and_is_idempotent(): void
    {
        $this->fakeGateway('rf-1');
        $user = User::factory()->create();
        $payment = $this->paidSubscriptionPayment($user);
        $this->assertTrue($user->fresh()->isPremium());

        /** @var PaymentService $svc */
        $svc = app(PaymentService::class);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $result = $svc->refund($payment, $admin, 'customer request');
        $this->assertEquals(PaymentStatus::Refunded, $result->status);
        $this->assertFalse($user->fresh()->isPremium());

        // Second refund is a no-op (idempotent).
        $again = $svc->refund($payment->fresh(), $admin);
        $this->assertEquals(PaymentStatus::Refunded, $again->status);
    }

    public function test_refund_claws_back_credits_without_negative(): void
    {
        $this->fakeGateway('rf-2');
        $user = User::factory()->create();
        CreditProduct::firstOrCreate(['code' => 'credits_100'], [
            'name' => '100 Credits', 'credits' => 100, 'price' => 99000,
            'currency' => 'IDR', 'is_active' => true,
        ]);
        /** @var PaymentService $svc */
        $svc = app(PaymentService::class);
        $payment = $svc->checkout($user, ['gateway' => 'midtrans', 'credit_product' => 'credits_100'])['payment'];
        $svc->fulfill($payment->fresh());
        $this->assertEquals(100, $user->fresh()->creditBalance());

        // Spend 70 first: clawback takes back only the remaining 30.
        app(CreditService::class)->spend($user->fresh(), 70, 'test spend');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $svc->refund($payment->fresh(), $admin);
        $this->assertEquals(0, $user->fresh()->creditBalance());
    }

    public function test_gateway_refund_failure_aborts(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/refund')) {
                return Http::response(['error' => 'nope'], 500);
            }

            return Http::response(['payment_url' => 'https://pay.test/x'], 200);
        });
        $user = User::factory()->create();
        $payment = $this->paidSubscriptionPayment($user);

        /** @var PaymentService $svc */
        $svc = app(PaymentService::class);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        try {
            $svc->refund($payment, $admin);
            $this->fail('Gateway failure should abort refund.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Gateway refund failed', $e->getMessage());
        }
        // Entitlements untouched: still paid + premium.
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue($user->fresh()->isPremium());
    }

    public function test_gateway_outage_returns_503_without_orphan_payment(): void
    {
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        $user = User::factory()->create();
        MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);

        $countBefore = Payment::count();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/checkout', [
            'gateway' => 'midtrans', 'subscription_plan' => 'premium_monthly',
        ])->assertStatus(503);
        $this->assertEquals($countBefore, Payment::count());
    }
}
