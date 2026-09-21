<?php

namespace App\Http\Controllers\Api\V1;

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
use App\Models\CreditProduct;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use App\Services\AiChatAssistantService;
use App\Services\AiMatchmakerService;
use App\Services\BoostService;
use App\Services\CreditService;
use App\Services\GiftService;
use App\Services\MembershipService;
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

    public function markNotifications(Request $request, \App\Services\NotificationService $notifications)
    {
        return response()->json(['marked' => $notifications->markAllRead($request->user())]);
    }

    public function plans(MembershipService $membership)
    {
        return response()->json($membership->plans());
    }

    public function subscriptions(Request $request)
    {
        return response()->json(SubscriptionResource::collection($request->user()->subscriptions()->with('plan')->latest('id')->paginate(20))->response()->getData());
    }

    public function checkout(CheckoutRequest $request, PaymentService $payments)
    {
        try {
            $result = $payments->checkout($request->user(), array_filter([
                'gateway' => $request->string('gateway'),
                'subscription_plan' => $request->input('subscription_plan', $request->input('plan_code')),
                'credit_product' => $request->input('credit_product'),
                'coupon_code' => $request->input('coupon_code'),
            ]));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['payment_id' => $result['payment']->id, 'gateway' => $result['gateway']], 201);
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
        event(new \App\Events\ReportCreated($report));

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

    public function boost(Request $request, BoostService $boost, CreditService $credits)
    {
        if ($boost->isLive($request->user())) {
            return response()->json(['live' => true]);
        }

        try {
            $credits->spend($request->user(), 50, 'Profile boost');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($boost->activate($request->user()), 201);
    }

    public function suggestedReplies(Request $request, \App\Models\Conversation $conversation, AiChatAssistantService $assistant)
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
}
