<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentAdminController extends Controller
{
    public function index(Request $request)
    {
        $items = Payment::with(['user', 'items'])->latest('id')->paginate(25);

        return $request->wantsJson() ? response()->json($items) : view('admin.membership.payments', ['items' => $items]);
    }

    public function show(Request $request, Payment $payment)
    {
        $payment->load(['user', 'items', 'webhooks']);

        return response()->json($payment);
    }

    public function refund(Request $request, Payment $payment, PaymentService $payments)
    {
        $this->authorize('refund', $payment);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        try {
            $result = $payments->refund($payment, $request->user(), $request->input('reason'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($result);
    }
}
