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
    ) {}

    /**
     * @param array{items?:array, subscription_plan?:string, credit_product?:string, gateway?:string, return_url?:string} $order
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

            $payment = Payment::create([
                'user_id' => $user->id,
                'gateway' => $gatewayCode,
                'amount' => $amount,
                'total_amount' => $amount,
                'currency' => config('payments.currency', 'IDR'),
                'status' => PaymentStatus::Pending,
            ]);
            foreach ($items as $it) {
                $payment->items()->create($it);
            }

            $driver = $this->gateways->driver($gatewayCode);
            $result = $driver->createPayment($payment->fresh(), $order);
            $this->audit->log('payment.created', $user, $payment, [], ['gateway' => $gatewayCode, 'total' => $amount]);

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
}
