<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\UserResource;
use App\Models\Like;
use App\Models\ProfileView;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\MatchingEngine;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $matches = UserMatch::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->where('is_active', true)->with(['userA.profile', 'userB.profile'])
            ->latest('matched_at')->paginate(20);

        $items = $matches->getCollection()->map(function (UserMatch $m) use ($user) {
            $other = (int) $m->user_a_id === (int) $user->id ? $m->userB : $m->userA;
            $other->setAttribute('compatibility_score', $m->compatibility_score);

            return $other;
        });

        if ($request->wantsJson()) {
            return MatchResource::collection($items)->response();
        }

        return view('member.matches', ['matches' => $matches]);
    }

    public function explain(Request $request, User $user, MatchingEngine $engine)
    {
        $viewer = $request->user();
        $this->authorize('view', $user);
        $explanation = $engine->explain($viewer, $user);

        return response()->json($explanation);
    }

    public function destroy(Request $request, User $user)
    {
        $me = $request->user();
        [$a, $b] = UserMatch::canonical((int) $me->id, (int) $user->id);
        UserMatch::where('user_a_id', $a)->where('user_b_id', $b)->where('is_active', true)
            ->update(['is_active' => false, 'unmatched_at' => now()]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Unmatched.'])
            : back()->with('status', 'Unmatched.');
    }

    /** Premium-only: members who liked you (mutual-not-yet). Free members get 403 + upgrade hint. */
    public function whoLiked(Request $request)
    {
        $user = $request->user();
        if (! $user->isPremium()) {
            return response()->json(['message' => 'Who Liked You is a Premium feature.', 'upgrade' => true], 403);
        }
        $likes = Like::with('liker.profile')
            ->where('liked_id', $user->id)
            ->whereNotIn('liker_id', fn ($q) => $q->select('liked_id')->from('likes')->where('liker_id', $user->id))
            ->latest('id')->paginate(20);

        return $request->wantsJson()
            ? UserResource::collection($likes->getCollection()->pluck('liker'))->response()
            : view('member.likes', ['whoLiked' => $likes]);
    }

    /** Premium-only: members who viewed your profile. */
    public function visitors(Request $request)
    {
        $user = $request->user();
        if (! $user->isPremium()) {
            return response()->json(['message' => 'Visitors is a Premium feature.', 'upgrade' => true], 403);
        }
        $views = ProfileView::with('viewer.profile')
            ->where('profile_user_id', $user->id)->latest('id')->paginate(20);

        return $request->wantsJson()
            ? UserResource::collection($views->getCollection()->pluck('viewer')->filter())->response()
            : view('member.visitors', ['visitors' => $views]);
    }
}
