<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\SubscriptionResource;
use App\Services\MembershipService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function plans(Request $request, MembershipService $membership)
    {
        $plans = $membership->plans();

        return $request->wantsJson()
            ? response()->json($plans)
            : view('member.premium.plans', ['plans' => $plans]);
    }

    public function current(Request $request)
    {
        $sub = $request->user()->subscriptions()->with('plan')->latest('id')->first();

        return response()->json($sub ? SubscriptionResource::make($sub) : null);
    }

    public function checkout(CheckoutRequest $request, PaymentService $payments)
    {
        $order = [
            'gateway' => $request->string('gateway'),
            'subscription_plan' => $request->input('subscription_plan', $request->input('plan_code')),
            'credit_product' => $request->input('credit_product'),
            'coupon_code' => $request->input('coupon_code'),
            'return_url' => $request->input('return_url'),
        ];

        try {
            $result = $payments->checkout($request->user(), array_filter($order));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'payment_id' => $result['payment']->id,
            'invoice' => $result['payment']->invoice_number,
            'gateway' => $result['gateway'],
        ], 201);
    }

    public function cancel(Request $request, int $subscription, SubscriptionService $subscriptions)
    {
        $sub = $request->user()->subscriptions()->findOrFail($subscription);
        $request->validate(['immediate' => ['nullable', 'boolean']]);
        $subscriptions->cancel($sub, $request->boolean('immediate'));

        return response()->json(['message' => 'Subscription cancelled.']);
    }
}
