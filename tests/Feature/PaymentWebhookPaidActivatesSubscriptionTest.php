<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookPaidActivatesSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_webhook_paid_activates_subscription_idempotently(): void
    {
        config()->set('payments.gateways.midtrans.server_key', 'test-server-key-123');

        $user = User::factory()->create();
        $plan = MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => 'midtrans',
            'amount' => 49000,
            'total_amount' => 49000,
            'currency' => 'IDR',
            'status' => PaymentStatus::Pending,
        ]);
        $payment->items()->create([
            'item_type' => 'subscription', 'item_id' => $plan->id,
            'name' => $plan->name, 'quantity' => 1, 'unit_price' => 49000, 'subtotal' => 49000,
        ]);

        $orderId = $payment->invoice_number;
        $statusCode = '200';
        $gross = '49000.00';
        $signature = hash('sha512', $orderId.$statusCode.$gross.'test-server-key-123');

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => 'settlement',
            'signature_key' => $signature,
        ];

        /** @var PaymentService $svc */
        $svc = app(PaymentService::class);

        $first = $svc->handleWebhook('midtrans', $payload);
        $this->assertTrue($first->is_processed);
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue($user->fresh()->isPremium());
        $this->assertEquals(1, Subscription::where('user_id', $user->id)->where('status', 'active')->count());

        // Replay same event_id must be idempotent: single activation.
        $second = $svc->handleWebhook('midtrans', $payload);
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, Subscription::where('user_id', $user->id)->where('status', 'active')->count());
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_webhook_http_endpoint_validates_hmac(): void
    {
        config()->set('payments.gateways.midtrans.server_key', 'test-server-key-123');
        $user = User::factory()->create();
        $plan = MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);
        $payment = Payment::create([
            'user_id' => $user->id, 'gateway' => 'midtrans',
            'amount' => 49000, 'total_amount' => 49000, 'currency' => 'IDR', 'status' => PaymentStatus::Pending,
        ]);
        $payment->items()->create([
            'item_type' => 'subscription', 'item_id' => $plan->id,
            'name' => $plan->name, 'quantity' => 1, 'unit_price' => 49000, 'subtotal' => 49000,
        ]);

        $payload = [
            'order_id' => $payment->invoice_number,
            'status_code' => '200',
            'gross_amount' => '49000.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', $payment->invoice_number.'200'.'49000.00'.'test-server-key-123'),
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk()->assertJson(['message' => 'ok']);
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
    }
}
