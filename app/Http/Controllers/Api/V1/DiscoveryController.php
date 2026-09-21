<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\DiscoveryService;
use App\Services\LikeService;
use App\Services\MatchingEngine;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    public function discover(Request $request, DiscoveryService $discovery)
    {
        $result = $discovery->discover($request->user(), $request->only([
            'gender', 'city', 'education', 'verified', 'online', 'premium',
            'min_age', 'max_age', 'max_distance_km', 'sort',
        ]), (int) $request->input('per_page', 20), $request->query('cursor'));

        return UserResource::collection($result)->response();
    }

    public function matches(Request $request)
    {
        $user = $request->user();
        $matches = UserMatch::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->where('is_active', true)->with(['userA', 'userB'])->latest('matched_at')->paginate(20);
        $items = $matches->getCollection()->map(function (UserMatch $m) use ($user) {
            $other = (int) $m->user_a_id === (int) $user->id ? $m->userB : $m->userA;
            $other->setAttribute('compatibility_score', $m->compatibility_score);

            return $other;
        });

        return MatchResource::collection($items)->response();
    }

    public function explain(Request $request, User $user, MatchingEngine $engine)
    {
        $this->authorize('view', $user);

        return response()->json($engine->explain($request->user(), $user));
    }

    public function scoreCache(Request $request, User $user, MatchingEngine $engine)
    {
        $this->authorize('view', $user);
        $ttl = (int) $request->input('ttl', 300);
        $result = $engine->scoreWithCache($request->user(), $user, $ttl);

        return response()->json($result);
    }

    public function batchScore(Request $request, MatchingEngine $engine)
    {
        $request->validate(['candidate_ids' => 'required|array', 'candidate_ids.*' => 'integer|exists:users,id', 'limit' => 'nullable|integer|min:1|max:100']);
        $candidates = array_slice($request->input('candidate_ids'), 0, $request->input('limit', 50));
        $result = $engine->batchScore($request->user(), $candidates, $request->input('limit', 50));

        return response()->json($result);
    }

    public function like(Request $request, User $user, LikeService $likes)
    {
        $this->authorize('view', $user);
        try {
            return response()->json($likes->like($request->user(), $user), 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'upgrade' => true], 403);
        }
    }

    public function likeQuota(Request $request, LikeService $likes)
    {
        return response()->json([
            'remaining' => $likes->likesRemainingToday($request->user()),
            'is_premium' => (bool) $request->user()->isPremium(),
        ]);
    }

    public function pass(Request $request, User $user, LikeService $likes)
    {
        $this->authorize('view', $user);
        $likes->pass($request->user(), $user);

        return response()->json(['message' => 'Passed.']);
    }

    public function superlike(Request $request, User $user, LikeService $likes)
    {
        $this->authorize('view', $user);
        $request->validate(['message' => ['nullable', 'string', 'max:500']]);

        return response()->json($likes->superLike($request->user(), $user, $request->input('message')), 201);
    }

    public function favorite(Request $request, User $user, LikeService $likes)
    {
        $this->authorize('view', $user);

        return response()->json($likes->favorite($request->user(), $user), 201);
    }

    public function unfavorite(Request $request, User $user, LikeService $likes)
    {
        $likes->unfavorite($request->user(), $user);

        return response()->json(['message' => 'Removed.']);
    }

    public function rewind(Request $request, LikeService $likes)
    {
        return response()->json($likes->rewind($request->user()) ?? ['message' => 'Nothing to rewind.']);
    }
}
