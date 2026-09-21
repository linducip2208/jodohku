<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
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
}
