<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\UserResource;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\ProfileView;
use App\Models\Rewind;
use App\Models\SuperLike;
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
        $validated = $request->validate([
            'gender' => ['nullable', 'in:male,female'],
            'city' => ['nullable', 'string', 'max:120'],
            'education' => ['nullable', 'string', 'max:120'],
            'religion' => ['nullable', 'string', 'max:120'],
            'marital_status' => ['nullable', 'string', 'max:60'],
            'relationship_goal' => ['nullable', 'string', 'max:120'],
            'height_min' => ['nullable', 'integer', 'min:100', 'max:250'],
            'height_max' => ['nullable', 'integer', 'min:100', 'max:250'],
            'has_photo' => ['nullable', 'boolean'],
            'keyword' => ['nullable', 'string', 'max:120'],
            'verified' => ['nullable', 'boolean'],
            'online' => ['nullable', 'boolean'],
            'premium' => ['nullable', 'boolean'],
            'min_age' => ['nullable', 'integer', 'min:17', 'max:80'],
            'max_age' => ['nullable', 'integer', 'min:17', 'max:80'],
            'max_distance_km' => ['nullable', 'integer', 'min:5', 'max:2000'],
            'sort' => ['nullable', 'in:newest,compatibility,active,distance,popularity'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        if (isset($validated['min_age'], $validated['max_age']) && $validated['min_age'] > $validated['max_age']) {
            return response()->json(['message' => 'min_age cannot exceed max_age.'], 422);
        }
        $result = $discovery->discover($request->user(), $validated, (int) ($validated['per_page'] ?? 20), $request->query('cursor'));

        return UserResource::collection($result)->response();
    }

    public function picks(Request $request, DiscoveryService $discovery)
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:20']]);

        return response()->json([
            'date' => today()->toDateString(),
            'picks' => $discovery->dailyPicks($request->user(), (int) $request->input('limit', 10)),
        ]);
    }

    public function resetPicks(Request $request, DiscoveryService $discovery)
    {
        $discovery->resetDailyPicks($request->user());

        return response()->json(['message' => 'Daily picks reset.']);
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
        $ttl = max(60, min(3600, $ttl));
        $result = $engine->scoreWithCache($request->user(), $user, $ttl);

        return response()->json($result);
    }

    public function batchScore(Request $request, MatchingEngine $engine)
    {
        $request->validate([
            'candidate_ids' => 'required|array|max:100',
            'candidate_ids.*' => 'integer|exists:users,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        $candidates = array_values(array_unique(array_slice($request->input('candidate_ids'), 0, $request->input('limit', 50))));
        // Privacy: never score incognito/blocked/inactive users via batch enumeration.
        $me = $request->user();
        $candidates = array_values(array_filter($candidates, fn ($id) => $id !== (int) $me->id && $engine->passesHardFilter($me, User::find($id) ?? $me)));
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

    public function unlike(Request $request, User $user, LikeService $likes)
    {
        $likes->unlike($request->user(), $user);

        return response()->json(['message' => 'Like removed.']);
    }

    public function superlike(Request $request, User $user, LikeService $likes)
    {
        $this->authorize('view', $user);
        $request->validate(['message' => ['nullable', 'string', 'max:500']]);
        try {
            return response()->json($likes->superLike($request->user(), $user, $request->input('message')), 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'upgrade' => true], 403);
        }
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
        try {
            return response()->json($likes->rewind($request->user()) ?? ['message' => 'Nothing to rewind.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $today = today();

        return response()->json([
            'profiles_viewed_today' => ProfileView::where('viewer_id', $user->id)->where('created_at', '>=', $today)->count(),
            'profiles_viewed_total' => ProfileView::where('viewer_id', $user->id)->count(),
            'likes_given_today' => Like::where('liker_id', $user->id)->whereDate('created_at', $today)->count(),
            'likes_given_total' => Like::where('liker_id', $user->id)->count(),
            'likes_received_today' => Like::where('liked_id', $user->id)->whereDate('created_at', $today)->count(),
            'likes_received_total' => Like::where('liked_id', $user->id)->count(),
            'matches_today' => UserMatch::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))->whereDate('matched_at', $today)->where('is_active', true)->count(),
            'matches_total' => UserMatch::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))->where('is_active', true)->count(),
            'rewinds_total' => Rewind::where('user_id', $user->id)->whereNull('undone_at')->count(),
            'favorites_total' => Favorite::where('user_id', $user->id)->count(),
            'superlikes_given_total' => SuperLike::where('sender_id', $user->id)->count(),
            'superlikes_received_total' => SuperLike::where('receiver_id', $user->id)->count(),
        ]);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'type' => ['nullable', 'in:all,likes,superlikes'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'target_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $type = $request->query('type', 'all');
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $query = Like::where('liker_id', $user->id)->with(['liked']);

        match ($type) {
            'likes' => $query->where('is_super', false),
            'superlikes' => $query->where('is_super', true),
            default => $query,
        };

        if ($request->query('target_id')) {
            $query->where('liked_id', (int) $request->query('target_id'));
        }

        $items = $query->latest('id')->paginate($perPage);

        return response()->json($items);
    }
}
