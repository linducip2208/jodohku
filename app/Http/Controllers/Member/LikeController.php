<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CreditService;
use App\Services\LikeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    public function like(Request $request, User $user, LikeService $likes)
    {
        $this->authorize('view', $user);
        try {
            $result = $likes->like($request->user(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'upgrade' => true], 403);
        }

        return response()->json([
            'like_id' => $result['like']->id,
            'is_new_match' => $result['is_new_match'],
            'match_id' => $result['match']?->id,
        ], $result['like']->wasRecentlyCreated ? 201 : 200);
    }

    public function unlike(Request $request, User $user, LikeService $likes)
    {
        $likes->unlike($request->user(), $user);

        return response()->json(['message' => 'Unliked.']);
    }

    public function pass(Request $request, User $user, LikeService $likes)
    {
        $this->authorize('view', $user);
        $likes->pass($request->user(), $user);

        return response()->json(['message' => 'Passed.']);
    }

    public function superlike(Request $request, User $user, LikeService $likes, CreditService $credits)
    {
        $this->authorize('view', $user);
        $request->validate(['message' => ['nullable', 'string', 'max:500']]);
        $result = DB::transaction(function () use ($request, $user, $likes, $credits) {
            try {
                $credits->spend($request->user(), 25, 'Super like');
            } catch (\Throwable) {
                // free quota fallback: continue without charge
            }

            return $likes->superLike($request->user(), $user, $request->input('message'));
        });

        return response()->json(['super_like_id' => $result['super_like']->id, 'is_new_match' => $result['is_new_match']], 201);
    }

    public function favorite(Request $request, User $user, LikeService $likes)
    {
        $this->authorize('view', $user);
        $fav = $likes->favorite($request->user(), $user);

        return response()->json(['favorite_id' => $fav->id], 201);
    }

    public function unfavorite(Request $request, User $user, LikeService $likes)
    {
        $likes->unfavorite($request->user(), $user);

        return response()->json(['message' => 'Removed from favorites.']);
    }

    public function rewind(Request $request, LikeService $likes)
    {
        $result = $likes->rewind($request->user());
        if (! $result) {
            return response()->json(['message' => 'Nothing to rewind.'], 422);
        }

        return response()->json($result);
    }
}
