<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = $request->user()->payments()->with('items')->latest('id')->paginate(20);

        return $request->wantsJson()
            ? response()->json(PaymentResource::collection($payments)->response()->getData())
            : view('member.payments', ['payments' => $payments]);
    }

    public function show(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);
        $payment->load(['items', 'user', 'webhooks']);

        return response()->json(PaymentResource::make($payment));
    }

    public function receipt(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);
        if ($payment->status !== 'paid') {
            return response()->json(['message' => 'Receipt available only for paid payments.'], 422);
        }
        $sub = $payment->subscription ? $payment->subscription->load('plan') : null;

        return response()->json([
            'payment' => $payment,
            'items' => $payment->items,
            'subscription' => $sub,
            'receipt_number' => 'RT-'.$payment->invoice_number,
            'issued_at' => $payment->paid_at,
        ]);
    }

    public function callback(Request $request, string $gateway)
    {
        if (! in_array($gateway, ['ipaymu', 'xendit', 'midtrans', 'tripay'], true)) {
            abort(404);
        }
        ProcessPaymentWebhook::dispatch($gateway, $request->all(), $request->headers->all());

        return response()->json(['message' => 'Webhook queued.']);
    }

    public function retry(Request $request, Payment $payment, PaymentService $payments)
    {
        $this->authorize('update', $payment);
        if ($payment->status !== 'failed') {
            return response()->json(['message' => 'Only failed payments can be retried.'], 422);
        }
        try {
            $result = $payments->checkout($payment->user, ['subscription_plan' => $payment->subscription?->membership_plan?->code]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['payment_id' => $result['payment']->id, 'gateway' => $result['gateway']], 201);
    }
}
