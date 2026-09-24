<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * K4: duplicate/out-of-order gateway callback flood.
 *
 * Gateways retry aggressively; Midtrans may deliver `capture` then
 * `settlement` for one order (distinct event_ids) or replay the same
 * payload N times. Exactly one entitlement must result: event_id dedup +
 * row locks + Paid-status guards in PaymentService.
 */
class WebhookFloodTest extends TestCase
{
    use RefreshDatabase;

    protected string $serverKey = 'flood-test-key';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('payments.gateways.midtrans.server_key', $this->serverKey);
    }

    protected function makePayment(User $user): Payment
    {
        $plan = MembershipPlan::firstOrCreate(['code' => 'premium_monthly'], [
            'name' => 'Premium', 'price' => 49000, 'currency' => 'IDR',
            'interval' => 'monthly', 'duration_days' => 30, 'is_active' => true,
        ]);
        $payment = Payment::create([
            'user_id' => $user->id, 'gateway' => 'midtrans',
            'amount' => 49000, 'total_amount' => 49000,
            'currency' => 'IDR', 'status' => PaymentStatus::Pending,
        ]);
        $payment->items()->create([
            'item_type' => 'subscription', 'item_id' => $plan->id,
            'name' => $plan->name, 'quantity' => 1, 'unit_price' => 49000, 'subtotal' => 49000,
        ]);

        return $payment;
    }

    protected function payload(Payment $payment, string $trx): array
    {
        $orderId = $payment->invoice_number;

        return [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => '49000.00',
            'transaction_status' => $trx,
            'signature_key' => hash('sha512', $orderId.'200'.'49000.00'.$this->serverKey),
        ];
    }

    public function test_duplicate_settlement_flood_fulfills_once(): void
    {
        $user = User::factory()->create();
        $payment = $this->makePayment($user);
        $svc = app(PaymentService::class);
        $payload = $this->payload($payment, 'settlement');

        // Same payload 5x (gateway retry storm, same event_id).
        for ($i = 0; $i < 5; $i++) {
            $wh = $svc->handleWebhook('midtrans', $payload);
            $this->assertTrue($wh->is_processed);
        }

        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertEquals(1, Subscription::where('user_id', $user->id)->where('status', 'active')->count());
        $this->assertEquals(1, PaymentWebhook::where('gateway', 'midtrans')
            ->where('payload->event_id', 'midtrans:'.$payment->invoice_number.':settlement')->count());

        // Same flood over HTTP: still ok, still single activation.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/webhooks/midtrans', $payload)->assertOk();
        }
        $this->assertEquals(1, Subscription::where('user_id', $user->id)->where('status', 'active')->count());
    }

    public function test_out_of_order_capture_settlement_then_stale_pending(): void
    {
        $user = User::factory()->create();
        $payment = $this->makePayment($user);
        $svc = app(PaymentService::class);

        // capture + settlement are DISTINCT event_ids for one order.
        $svc->handleWebhook('midtrans', $this->payload($payment, 'capture'));
        $svc->handleWebhook('midtrans', $this->payload($payment, 'settlement'));
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertEquals(1, Subscription::where('user_id', $user->id)->where('status', 'active')->count());

        // Stale pending arriving late must not downgrade or duplicate.
        $svc->handleWebhook('midtrans', $this->payload($payment, 'pending'));
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertEquals(1, Subscription::where('user_id', $user->id)->where('status', 'active')->count());
        $this->assertTrue($user->fresh()->isPremium());
    }
}
