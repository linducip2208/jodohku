<?php

namespace App\Services;

use App\Enums\CreditTxnType;
use App\Enums\PaymentStatus;
use App\Events\CreditsPurchased;
use App\Events\PaymentPaid;
use App\Models\CreditProduct;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected SubscriptionService $subscriptions,
        protected CreditService $credits,
        protected AuditService $audit,
        protected CouponService $coupons,
    ) {}

    /**
     * @param  array{items?:array, subscription_plan?:string, credit_product?:string, gateway?:string, return_url?:string, coupon_code?:string}  $order
     */
    public function checkout(User $user, array $order): array
    {
        return DB::transaction(function () use ($user, $order) {
            $gatewayCode = $order['gateway'] ?? config('payments.default', 'midtrans');
            $items = $order['items'] ?? [];
            $amount = 0;

            $plan = null;
            if (! empty($order['subscription_plan'])) {
                $plan = MembershipPlan::where('code', $order['subscription_plan'])->firstOrFail();
                $amount += (float) $plan->price;
                $items[] = ['item_type' => 'subscription', 'item_id' => $plan->id, 'name' => $plan->name, 'quantity' => 1, 'unit_price' => (float) $plan->price, 'subtotal' => (float) $plan->price];
            }
            $product = null;
            if (! empty($order['credit_product'])) {
                $product = CreditProduct::where('code', $order['credit_product'])->firstOrFail();
                $amount += (float) $product->price;
                $items[] = ['item_type' => 'credits', 'item_id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'unit_price' => (float) $product->price, 'subtotal' => (float) $product->price];
            }
            if (empty($items)) {
                throw new \InvalidArgumentException('Order must contain items.');
            }

            // Coupon is validated BEFORE the payment row exists so failures
            // never leave orphan pending payments.
            $coupon = null;
            $discount = 0.0;
            if (! empty($order['coupon_code'])) {
                ['coupon' => $coupon, 'discount' => $discount] = $this->coupons->quote($user, (string) $order['coupon_code'], (float) $amount);
                if ($discount > 0) {
                    $items[] = ['item_type' => 'discount', 'item_id' => $coupon->id, 'name' => 'Coupon '.$coupon->code, 'quantity' => 1, 'unit_price' => -$discount, 'subtotal' => -$discount];
                }
            }
            $total = round(max((float) $amount - $discount, 0), 2);

            $payment = Payment::create([
                'user_id' => $user->id,
                'gateway' => $gatewayCode,
                'amount' => $amount,
                'total_amount' => $total,
                'currency' => config('payments.currency', 'IDR'),
                'status' => PaymentStatus::Pending,
            ]);
            foreach ($items as $it) {
                $payment->items()->create($it);
            }
            if ($coupon && $discount > 0) {
                $this->coupons->recordRedemption($coupon, $user, (int) $payment->id, (float) $discount);
            }

            $driver = $this->gateways->driver($gatewayCode);
            try {
                $result = $driver->createPayment($payment->fresh(), $order);
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Throwable $e) {
                // HTTP/network/gateway failures: roll back so no orphan
                // pending payment is left behind; controllers map to 503.
                throw new \RuntimeException('Payment gateway unavailable: '.$e->getMessage());
            }
            $this->audit->log('payment.created', $user, $payment, [], ['gateway' => $gatewayCode, 'total' => $total, 'coupon' => $coupon?->code]);

            return ['payment' => $payment->fresh(), 'gateway' => $result];
        });
    }

    /** Idempotent webhook entrypoint. */
    public function handleWebhook(string $gatewayCode, array $payload, array $headers = []): PaymentWebhook
    {
        $driver = $this->gateways->driver($gatewayCode);
        $normalized = $driver->handleWebhook($payload, $headers);
        $eventId = $normalized['event_id'] ?? $gatewayCode.':'.md5(json_encode($payload));

        // Idempotency: skip if same event already processed
        $existing = PaymentWebhook::where('gateway', $gatewayCode)
            ->where('payload->event_id', $eventId)->first()
            ?? PaymentWebhook::where('gateway', $gatewayCode)->whereJsonContains('payload', ['event_id' => $eventId])->first();
        if ($existing && $existing->is_processed) {
            return $existing;
        }

        return DB::transaction(function () use ($gatewayCode, $payload, $normalized, $eventId, $existing) {
            $payment = null;
            if (! empty($normalized['reference'])) {
                $payment = Payment::where('invoice_number', $normalized['reference'])
                    ->orWhere('gateway_transaction_id', $normalized['reference'])->first();
            }
            $webhook = $existing ?? PaymentWebhook::create([
                'gateway' => $gatewayCode,
                'payment_id' => $payment?->id,
                'event_type' => $normalized['status'] ?? 'unknown',
                'payload' => array_merge($payload, ['event_id' => $eventId]),
            ]);

            if (($normalized['status'] ?? '') === 'paid' && $payment && $payment->status !== PaymentStatus::Paid) {
                $this->fulfill($payment);
            }
            $webhook->markProcessed(200);

            return $webhook->fresh();
        });
    }

    public function fulfill(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

            foreach ($payment->items as $item) {
                if ($item->item_type === 'subscription' && $item->item_id) {
                    $plan = MembershipPlan::find($item->item_id);
                    if ($plan) {
                        $sub = $this->subscriptions->activate($payment->user, $plan);
                        $payment->update(['subscription_id' => $sub->id]);
                    }
                }
                if ($item->item_type === 'credits' && $item->item_id) {
                    $product = CreditProduct::find($item->item_id);
                    if ($product) {
                        $this->credits->record($payment->user, CreditTxnType::Purchase, $product->totalCredits(), 'Credit purchase '.$product->code, [
                            'reference_type' => Payment::class, 'reference_id' => $payment->id,
                        ]);
                        event(new CreditsPurchased($payment->user, $product->totalCredits()));
                    }
                }
            }

            $this->audit->log('payment.paid', $payment->user, $payment);
            event(new PaymentPaid($payment->fresh()));

            return $payment->fresh();
        });
    }

    /**
     * Reverse a paid payment: gateway refund first (aborts on gateway
     * failure so money and goods never diverge), then revoke entitlements
     * granted by fulfill(). Idempotent: already-refunded returns as-is.
     */
    public function refund(Payment $payment, User $actor, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            $payment->refresh();
            if ($payment->status === PaymentStatus::Refunded) {
                return $payment;
            }
            if ($payment->status !== PaymentStatus::Paid) {
                throw new \InvalidArgumentException('Only paid payments can be refunded.');
            }

            $driver = $this->gateways->driver($payment->gateway);
            try {
                $gwResult = $driver->refund($payment);
            } catch (\Throwable $e) {
                throw new \RuntimeException('Gateway refund failed: '.$e->getMessage());
            }

            // Revoke subscription granted by this payment.
            if ($payment->subscription_id) {
                $sub = Subscription::find($payment->subscription_id);
                if ($sub) {
                    $this->subscriptions->cancel($sub, true);
                }
            }

            // Claw back granted credits without ever going negative: take
            // back at most the current balance, shortfall is audit-logged.
            foreach ($payment->items()->where('item_type', 'credits')->get() as $item) {
                $product = $item->item_id ? CreditProduct::find($item->item_id) : null;
                $granted = $product ? $product->totalCredits() : 0;
                if ($granted <= 0) {
                    continue;
                }
                $balance = $this->credits->balance($payment->user);
                $clawback = min($granted, $balance);
                if ($clawback > 0) {
                    $this->credits->record($payment->user, CreditTxnType::Adjustment, $clawback, 'Refund clawback payment '.$payment->invoice_number, [
                        'reference_type' => Payment::class, 'reference_id' => $payment->id,
                    ]);
                }
                if ($clawback < $granted) {
                    $this->audit->log('payment.refund_shortfall', $actor, $payment, [], [
                        'granted' => $granted, 'clawed_back' => $clawback,
                    ]);
                }
            }

            $payment->update(['status' => PaymentStatus::Refunded, 'refunded_at' => now()]);
            $this->audit->log('payment.refunded', $actor, $payment, ['status' => 'paid'], [
                'reason' => $reason, 'gateway' => $gwResult,
            ]);

            return $payment->fresh();
        });
    }
}
