<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\AuditService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionAdminController extends Controller
{
    public function index(Request $request)
    {
        $items = Subscription::with(['user', 'plan'])->latest('id')->paginate(25);

        return $request->wantsJson() ? response()->json($items) : view('admin.membership.subscriptions', ['items' => $items]);
    }

    public function cancel(Request $request, Subscription $subscription, SubscriptionService $subscriptions, AuditService $audit)
    {
        $subscriptions->cancel($subscription, $request->boolean('immediate'));

        return response()->json(['message' => 'Cancelled.']);
    }

    public function extend(Request $request, Subscription $subscription, AuditService $audit)
    {
        $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365']]);
        $subscription->update(['ends_at' => ($subscription->ends_at ?? now())->copy()->addDays((int) $request->input('days'))]);
        $audit->log('admin.subscription.extended', $request->user(), $subscription, [], ['days' => $request->input('days')]);

        return response()->json($subscription->fresh());
    }
}
