<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Events\ReportCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\CreditSpendRequest;
use App\Http\Requests\ReportRequest;
use App\Http\Requests\VerificationSubmitRequest;
use App\Http\Resources\CreditWalletResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Coupon;
use App\Models\CreditProduct;
use App\Models\CreditTransaction;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use App\Services\AiChatAssistantService;
use App\Services\AiMatchmakerService;
use App\Services\BoostService;
use App\Services\CouponService;
use App\Services\CreditService;
use App\Services\GiftService;
use App\Services\MembershipService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use App\Services\VerificationService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function notifications(Request $request)
    {
        return response()->json(NotificationResource::collection($request->user()->notifications()->latest('created_at')->paginate(20))->response()->getData());
    }

    public function markNotifications(Request $request, NotificationService $notifications)
    {
        return response()->json(['marked' => $notifications->markAllRead($request->user())]);
    }

    public function plans(MembershipService $membership)
    {
        return response()->json($membership->plans());
    }

    /** Claim the once-ever free trial (brand-catalog aware). */
    public function trial(Request $request, SubscriptionService $subs)
    {
        if (! $subs->trialEligible($request->user())) {
            return response()->json(['message' => 'Trial sudah pernah dipakai.'], 422);
        }
        try {
            $sub = $subs->startTrial($request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($sub->fresh(), 201);
    }

    public function subscriptions(Request $request)
    {
        return response()->json(SubscriptionResource::collection($request->user()->subscriptions()->with('plan')->latest('id')->paginate(20))->response()->getData());
    }

    public function checkout(CheckoutRequest $request, PaymentService $payments)
    {
        try {
            $result = $payments->checkout($request->user(), array_filter([
                'gateway' => $request->string('gateway')->toString(),
                'subscription_plan' => $request->input('subscription_plan', $request->input('plan_code')),
                'credit_product' => $request->input('credit_product'),
                'coupon_code' => $request->input('coupon_code'),
            ], fn ($v) => $v !== null && $v !== ''));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['payment_id' => $result['payment']->id, 'gateway' => $result['gateway']], 201);
    }

    public function checkoutQuote(Request $request, PaymentService $payments)
    {
        $request->validate([
            'gateway' => ['nullable', 'string', 'max:30'],
            'subscription_plan' => ['nullable', 'string', 'max:60'],
            'plan_code' => ['nullable', 'string', 'max:60'],
            'credit_product' => ['nullable', 'string', 'max:60'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
        ]);
        try {
            $quote = $payments->estimate($request->user(), array_filter([
                'gateway' => $request->input('gateway'),
                'subscription_plan' => $request->input('subscription_plan', $request->input('plan_code')),
                'credit_product' => $request->input('credit_product'),
                'coupon_code' => $request->input('coupon_code'),
            ], fn ($v) => $v !== null && $v !== ''));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($quote);
    }

    public function payments(Request $request)
    {
        return response()->json(PaymentResource::collection($request->user()->payments()->latest('id')->paginate(20))->response()->getData());
    }

    public function payment(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);

        return response()->json(PaymentResource::make($payment->load('items')));
    }

    public function receipt(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);
        if ($payment->status !== PaymentStatus::Paid) {
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

    public function retry(Request $request, Payment $payment, PaymentService $payments)
    {
        $this->authorize('view', $payment);
        // Retry is a member self-action: staff may VIEW payments in admin,
        // but must never mint new payment rows on another member's behalf.
        abort_unless((int) $payment->user_id === (int) $request->user()->id, 403);
        if ($payment->status !== PaymentStatus::Failed) {
            return response()->json(['message' => 'Only failed payments can be retried.'], 422);
        }
        // Rebuild the full original order (plan + credits + coupon + gateway).
        $order = ['gateway' => $payment->gateway];
        foreach ($payment->items as $item) {
            if ($item->item_type === 'subscription' && $item->item_id) {
                $plan = MembershipPlan::find($item->item_id);
                if ($plan) {
                    $order['subscription_plan'] = $plan->code;
                }
            }
            if ($item->item_type === 'credits' && $item->item_id) {
                $product = CreditProduct::find($item->item_id);
                if ($product) {
                    $order['credit_product'] = $product->code;
                }
            }
            if ($item->item_type === 'discount' && $item->item_id) {
                $coupon = Coupon::find($item->item_id);
                if ($coupon) {
                    $order['coupon_code'] = $coupon->code;
                }
            }
        }
        try {
            $result = $payments->checkout($payment->user, $order);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['payment_id' => $result['payment']->id, 'gateway' => $result['gateway']], 201);
    }

    public function cancelSubscription(Request $request, int $subscription, SubscriptionService $subscriptions)
    {
        $sub = $request->user()->subscriptions()->findOrFail($subscription);
        $subscriptions->cancel($sub, $request->boolean('immediate'));

        return response()->json(['message' => 'Cancelled.']);
    }

    public function wallet(Request $request, CreditService $credits)
    {
        return response()->json(CreditWalletResource::make($credits->wallet($request->user())));
    }

    public function creditProducts()
    {
        return response()->json(CreditProduct::active()->get());
    }

    public function spend(CreditSpendRequest $request, CreditService $credits)
    {
        try {
            $txn = $credits->spend($request->user(), (int) $request->input('amount'), (string) $request->input('description', 'Spend'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($txn, 201);
    }

    public function verify(VerificationSubmitRequest $request, VerificationService $verification)
    {
        $req = $verification->submit($request->user(), $request->string('type'), (array) $request->input('documents', []), $request->input('notes'));

        return response()->json($req, 201);
    }

    public function verificationStatus(Request $request, VerificationService $verification)
    {
        return response()->json(['requests' => $verification->statusFor($request->user())]);
    }

    public function report(ReportRequest $request)
    {
        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $request->input('reported_user_id'),
            'reportable_type' => $request->input('reportable_type'),
            'reportable_id' => $request->input('reportable_id'),
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'status' => 'pending',
        ]);
        event(new ReportCreated($report));

        return response()->json($report, 201);
    }

    public function block(Request $request, User $user)
    {
        $this->authorize('view', $user);
        Block::firstOrCreate(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id]);

        return response()->json(['message' => 'Blocked.'], 201);
    }

    public function unblock(Request $request, User $user)
    {
        Block::where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->delete();

        return response()->json(['message' => 'Unblocked.']);
    }

    public function blocks(Request $request)
    {
        return response()->json(Block::where('blocker_id', $request->user()->id)->paginate(20));
    }

    public function gifts(GiftService $gifts)
    {
        return response()->json($gifts->catalog());
    }

    public function giftsReceived(Request $request, GiftService $gifts)
    {
        return response()->json($gifts->received($request->user(), (int) $request->query('per_page', 25))->items());
    }

    public function giftsSent(Request $request, GiftService $gifts)
    {
        return response()->json($gifts->sent($request->user(), (int) $request->query('per_page', 25))->items());
    }

    public function giftsStats(Request $request, GiftService $gifts)
    {
        return response()->json($gifts->stats($request->user()));
    }

    public function giftsLeaderboard(Request $request, GiftService $gifts)
    {
        $request->validate(['period' => ['nullable', 'string', 'in:all,month,week'], 'limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return response()->json($gifts->leaderboard(
            $request->input('period', 'all'), (int) $request->input('limit', 10)
        ));
    }

    public function giftsTrending(Request $request, GiftService $gifts)
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return response()->json($gifts->trending((int) $request->input('limit', 10)));
    }

    public function plansMatrix(MembershipService $membership)
    {
        return response()->json($membership->featureMatrix());
    }

    public function plansFeatures(Request $request, MembershipService $membership)
    {
        return response()->json($membership->currentFeatures($request->user()));
    }

    public function aiOpeners(Request $request, User $user, AiChatAssistantService $assistant)
    {
        $this->authorize('view', $user);

        return response()->json(['openers' => $assistant->openers($request->user(), $user)]);
    }

    public function aiDigest(Request $request, Conversation $conversation, AiChatAssistantService $assistant)
    {
        $this->authorize('view', $conversation);

        return response()->json(['digest' => $assistant->digest($conversation, $request->user())]);
    }

    public function storeGift(Request $request, GiftService $gifts)
    {
        $request->validate([
            'gift' => ['required', 'string'],
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $receiver = User::findOrFail($request->integer('receiver_id'));
        $conversation = $request->filled('conversation_id')
            ? Conversation::findOrFail($request->integer('conversation_id'))
            : null;

        try {
            $txn = $gifts->send($request->user(), $receiver, (string) $request->input('gift'), (int) $request->input('quantity', 1), $conversation, null, $request->input('note'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($txn, 201);
    }

    public function boost(Request $request, BoostService $boost, CreditService $credits)
    {
        if ($boost->isLive($request->user())) {
            return response()->json(['live' => true]);
        }

        try {
            $credits->spend($request->user(), 50, 'Profile boost '.now()->format('YmdHi'), [
                'reference_type' => 'boost',
                'reference_id' => $request->user()->id,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($boost->activate($request->user()), 201);
    }

    public function boostStatus(Request $request, BoostService $boost)
    {
        return response()->json($boost->status($request->user()));
    }

    public function boostHistory(Request $request, BoostService $boost)
    {
        return response()->json($boost->history($request->user(), (int) $request->query('per_page', 20)));
    }

    public function quoteCoupon(Request $request, CouponService $coupons)
    {
        $request->validate([
            'coupon_code' => ['required', 'string', 'max:60'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);
        try {
            $quote = $coupons->quote($request->user(), (string) $request->input('coupon_code'), (float) $request->input('subtotal'));
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($quote);
    }

    public function suggestedReplies(Request $request, Conversation $conversation, AiChatAssistantService $assistant)
    {
        $this->authorize('view', $conversation);

        return response()->json(['replies' => $assistant->suggestedReplies($conversation, $request->user())]);
    }

    public function matchmaker(Request $request, AiMatchmakerService $matchmaker)
    {
        return response()->json($matchmaker->recommend($request->user(), (string) $request->input('question', ''), 5));
    }

    public function settings(Request $request)
    {
        return response()->json($request->user()->load(['notificationPreference', 'profilePrivacy']));
    }

    public function transactions(Request $request, CreditService $credits)
    {
        $request->validate([
            'type' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = CreditTransaction::where('user_id', $request->user()->id)->with('user');
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }
        $items = $query->latest('id')->paginate(25);

        return response()->json($items);
    }

    public function walletSummary(Request $request, CreditService $credits)
    {
        return response()->json($credits->summary($request->user()));
    }

    public function trialEligibility(Request $request, SubscriptionService $subscriptions, MembershipService $membership)
    {
        $request->validate(['plan_code' => ['nullable', 'string', 'max:60']]);
        $plan = $request->filled('plan_code') ? $membership->find($request->string('plan_code')) : null;
        if ($request->filled('plan_code') && ! $plan) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        return response()->json(['eligible' => $subscriptions->trialEligible($request->user(), $plan)]);
    }

    public function switchSubscription(Request $request, SubscriptionService $subscriptions, MembershipService $membership)
    {
        $request->validate(['plan_code' => ['required', 'string', 'max:60']]);
        $plan = $membership->findOrFail($request->string('plan_code'));
        try {
            $sub = $subscriptions->switchPlan($request->user(), $plan);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($sub, 201);
    }

    public function subscriptionHistory(Request $request)
    {
        $items = $request->user()->subscriptions()->with('plan')
            ->whereIn('status', ['expired', 'cancelled'])
            ->latest('id')->paginate(20);

        return response()->json($items);
    }

    public function paymentSummary(Request $request)
    {
        $monthStart = now()->startOfMonth();
        $paid = Payment::where('user_id', $request->user()->id)
            ->where('status', PaymentStatus::Paid)
            ->where('paid_at', '>=', $monthStart)
            ->sum('total_amount');
        $refunded = Payment::where('user_id', $request->user()->id)
            ->where('status', PaymentStatus::Refunded)
            ->where('refunded_at', '>=', $monthStart)
            ->sum('total_amount');
        $count = Payment::where('user_id', $request->user()->id)
            ->where('created_at', '>=', $monthStart)->count();

        return response()->json([
            'month' => now()->format('Y-m'),
            'total_paid' => $paid,
            'total_refunded' => $refunded,
            'net' => $paid - $refunded,
            'payment_count' => $count,
            'balance' => $request->user()->creditWallet->balance ?? 0,
        ]);
    }
}
