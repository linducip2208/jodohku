<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Payment;
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
        $payment->load('items');

        return response()->json(PaymentResource::make($payment));
    }

    public function callback(Request $request, string $gateway)
    {
        if (! in_array($gateway, ['ipaymu', 'xendit', 'midtrans', 'tripay'], true)) {
            abort(404);
        }
        ProcessPaymentWebhook::dispatch($gateway, $request->all(), $request->headers->all());

        return response()->json(['message' => 'Webhook queued.']);
    }
}
