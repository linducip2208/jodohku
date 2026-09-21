<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AuditService;
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

    public function refund(Request $request, Payment $payment, AuditService $audit)
    {
        $this->authorize('refund', $payment);
        $payment->update(['status' => 'refunded']);
        $audit->log('admin.payment.refunded', $request->user(), $payment);

        return response()->json(['message' => 'Refunded.']);
    }
}
