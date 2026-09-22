<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Message;
use App\Models\ModerationQueue;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\ProfileView;
use App\Models\Report;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function overview(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        return response()->json([
            'registrations' => User::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>', $since)->groupBy('d')->orderBy('d')->get(),
            'matches' => UserMatch::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>', $since)->groupBy('d')->orderBy('d')->get(),
            'messages' => Message::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>', $since)->groupBy('d')->orderBy('d')->get(),
            'revenue' => Payment::selectRaw('DATE(created_at) d, SUM(total_amount) c')->where('status', 'paid')->where('created_at', '>', $since)->groupBy('d')->orderBy('d')->get(),
        ]);
    }

    /** Headline KPIs used by the dashboard cards. */
    public function kpi(Request $request)
    {
        $days = (int) $request->input('days', 7);
        $since = now()->subDays($days);

        return response()->json([
            'users_total' => User::withTrashed()->count(),
            'users_active' => User::active()->count(),
            'users_new' => User::where('created_at', '>', $since)->count(),
            'users_dau' => User::whereNull('deleted_at')->where('last_active_at', '>', $since)->count(),
            'users_online' => User::whereNull('deleted_at')->where('is_online', true)->count(),
            'premium_total' => User::where('is_premium', true)->count(),
            'verified_total' => User::where('is_verified', true)->count(),
            'matches_total' => UserMatch::count(),
            'matches_new' => UserMatch::where('created_at', '>', $since)->count(),
            'messages_total' => Message::count(),
            'messages_new' => Message::where('created_at', '>', $since)->count(),
            'likes_total' => Like::count(),
            'likes_new' => Like::where('created_at', '>', $since)->count(),
            'profile_views_new' => ProfileView::where('created_at', '>', $since)->count(),
            'revenue_total' => (float) Payment::where('status', 'paid')->sum('total_amount'),
            'revenue_new' => (float) Payment::where('status', 'paid')->where('created_at', '>', $since)->sum('total_amount'),
            'pending_moderation' => ModerationQueue::whereNull('resolved_at')->count(),
            'open_reports' => Report::where('status', 'open')->count(),
        ]);
    }

    /** Revenue split by gateway + product mix. */
    public function gateways(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $since = now()->subDays($days);

        $byGateway = Payment::leftJoin('payment_gateways', 'payments.gateway_id', '=', 'payment_gateways.id')
            ->selectRaw('COALESCE(payment_gateways.name, payments.gateway) as gateway, COUNT(*) as txn, SUM(payments.total_amount) as revenue')
            ->where('payments.status', 'paid')
            ->when($days > 0, fn ($q) => $q->where('payments.created_at', '>', $since))
            ->groupBy('gateway')->orderByDesc('revenue')->get();

        $byItem = PaymentItem::leftJoin('payments', 'payment_items.payment_id', '=', 'payments.id')
            ->selectRaw('payment_items.item_type, COUNT(*) as total')
            ->where('payments.status', 'paid')
            ->groupBy('payment_items.item_type')->orderByDesc('total')->get();

        $byPlan = PaymentItem::leftJoin('membership_plans', function ($j) {
            $j->on('payment_items.item_type', '=', Expression::raw("'plan'"))
                ->whereColumn('payment_items.item_code', 'membership_plans.code');
        })->selectRaw('payment_items.item_code, COUNT(*) as total')->groupBy('payment_items.item_code')
            ->orderByDesc('total')->limit(10)->get();

        return response()->json(['by_gateway' => $byGateway, 'by_item_type' => $byItem, 'top_plans' => $byPlan]);
    }

    /** Most engaged members (likes given/received, messages, profile views). */
    public function topUsers(Request $request)
    {
        $limit = min(50, max(1, (int) $request->input('limit', 20)));

        $likesGiven = Like::with('liker')->selectRaw('liker_id, COUNT(*) as total')->groupBy('liker_id')
            ->orderByDesc('total')->limit($limit)->get();
        $likesReceived = Like::with('liked')->selectRaw('liked_id, COUNT(*) as total')->groupBy('liked_id')
            ->orderByDesc('total')->limit($limit)->get();
        $messagers = Message::with('sender')->selectRaw('sender_id, COUNT(*) as total')->groupBy('sender_id')
            ->orderByDesc('total')->limit($limit)->get();
        $viewed = ProfileView::with('profileUser')->selectRaw('profile_user_id, COUNT(*) as total')->groupBy('profile_user_id')
            ->orderByDesc('total')->limit($limit)->get();

        return response()->json([
            'most_liked' => $likesReceived->map(fn ($r) => ['user_id' => $r->liked_id, 'name' => $r->liked?->displayName(), 'count' => $r->total])->values(),
            'most_active_likers' => $likesGiven->map(fn ($r) => ['user_id' => $r->liker_id, 'name' => $r->liker?->displayName(), 'count' => $r->total])->values(),
            'most_chatty' => $messagers->map(fn ($r) => ['user_id' => $r->sender_id, 'name' => $r->sender?->displayName(), 'count' => $r->total])->values(),
            'most_profile_viewed' => $viewed->map(fn ($r) => ['user_id' => $r->profile_user_id, 'name' => $r->profileUser?->displayName(), 'count' => $r->total])->values(),
        ]);
    }

    /** Rolling activity engagement (likes, matches, messages, DAU per day). */
    public function engagement(Request $request)
    {
        $days = (int) $request->input('days', 14);
        $since = now()->subDays($days);

        $daysList = [];
        for ($i = $days; $i >= 0; $i--) {
            $daysList[now()->subDays($i)->toDateString()] = null;
        }

        $series = function ($q, $col = 'created_at') {
            return $q->selectRaw('DATE('.$col.') d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');
        };

        $likes = $series(Like::where('created_at', '>', $since));
        $matches = $series(UserMatch::where('created_at', '>', $since));
        $messages = $series(Message::where('created_at', '>', $since));

        // DAU = distinct users who sent a message, gave a like, or registered that day.
        $activeByDay = [];
        $collect = function ($rows, $dayCol, $userCol) use (&$activeByDay) {
            foreach ($rows as $row) {
                $activeByDay[$row->$dayCol][$row->$userCol] = true;
            }
        };
        $collect(Message::where('created_at', '>', $since)->selectRaw('DATE(created_at) d, sender_id u')->distinct()->get(), 'd', 'u');
        $collect(Like::where('created_at', '>', $since)->selectRaw('DATE(created_at) d, liker_id u')->distinct()->get(), 'd', 'u');
        $collect(User::where('created_at', '>', $since)->selectRaw('DATE(created_at) d, id u')->distinct()->get(), 'd', 'u');

        $rows = [];
        foreach ($daysList as $day => $_) {
            $rows[] = [
                'date' => $day,
                'likes' => (int) ($likes[$day] ?? 0),
                'matches' => (int) ($matches[$day] ?? 0),
                'messages' => (int) ($messages[$day] ?? 0),
                'dau' => count($activeByDay[$day] ?? []),
            ];
        }

        return response()->json($rows);
    }

    /** Signup-to-first-match conversion & engagement by cohort. */
    public function cohorts(Request $request)
    {
        $cohortRows = User::whereNull('deleted_at')->selectRaw('DATE(created_at) d, COUNT(*) registered')->groupBy('d')
            ->orderByDesc('d')->limit(30)->get();
        if ($cohortRows->isEmpty()) {
            return response()->json([]);
        }
        $oldest = $cohortRows->min('d');

        $usersByDay = User::whereNull('deleted_at')->whereDate('created_at', '>=', $oldest)
            ->selectRaw('DATE(created_at) d, id')->get()->groupBy('d')->map(fn ($g) => $g->pluck('id')->all());
        $allIds = $usersByDay->flatten()->unique()->values();

        $matchedByDay = [];
        UserMatch::where(fn ($q) => $q->whereIn('user_a_id', $allIds)->orWhereIn('user_b_id', $allIds))
            ->select(['user_a_id', 'user_b_id'])->get()->each(function ($m) use (&$matchedByDay) {
                $matchedByDay[$m->user_a_id] = true;
                $matchedByDay[$m->user_b_id] = true;
            });
        $messagedIds = Message::whereIn('sender_id', $allIds)->distinct()->pluck('sender_id')->flip();
        $paidIds = Payment::whereIn('user_id', $allIds)->where('status', 'paid')->distinct()->pluck('user_id')->flip();

        $cohorts = $cohortRows->map(function ($c) use ($usersByDay, $matchedByDay, $messagedIds, $paidIds) {
            $ids = $usersByDay->get($c->d, []);
            $matchedCount = collect($ids)->filter(fn ($id) => isset($matchedByDay[$id]))->count();
            $messaged = collect($ids)->filter(fn ($id) => $messagedIds->has($id))->count();
            $paid = collect($ids)->filter(fn ($id) => $paidIds->has($id))->count();

            return [
                'cohort_date' => $c->d,
                'registered' => (int) $c->registered,
                'with_match' => $matchedCount,
                'match_rate_pct' => $c->registered > 0 ? round($matchedCount / $c->registered * 100, 1) : 0,
                'with_message' => $messaged,
                'paying' => $paid,
            ];
        })->values();

        return response()->json($cohorts);
    }

    public function funnel(Request $request)
    {
        $total = User::count();

        return response()->json([
            'registered' => $total,
            'with_photo' => User::has('photos')->count(),
            'with_questionnaire' => User::has('questionnaireAnswers')->count(),
            'with_match' => User::where(fn ($q) => $q->whereHas('matchesA')->orWhereHas('matchesB'))->count(),
            'paying' => User::where('is_premium', true)->count(),
        ]);
    }
}
