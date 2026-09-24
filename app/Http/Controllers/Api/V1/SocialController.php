<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use App\Services\FeedService;
use App\Services\FollowService;
use App\Services\ReactionService;
use App\Services\SearchService;
use App\Services\SocialDatingRecommendationService;
use App\Services\StoryService;
use Illuminate\Http\Request;

/**
 * Social API: follow graph, feed, stories, reactions, search, groups.
 * Thin delegation to domain services (same code paths as web).
 */
class SocialController extends Controller
{
    public function follow(Request $request, User $user, FollowService $follows)
    {
        try {
            $follows->follow($request->user(), $user);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Following.']);
    }

    public function unfollow(Request $request, User $user, FollowService $follows)
    {
        $follows->unfollow($request->user(), $user);

        return response()->json(['message' => 'Unfollowed.']);
    }

    public function followers(Request $request, User $user)
    {
        return response()->json($user->followers()->with(['profile'])->paginate(20));
    }

    public function following(Request $request, User $user)
    {
        return response()->json($user->following()->with(['profile'])->paginate(20));
    }

    public function suggested(Request $request, FollowService $follows)
    {
        return response()->json($follows->suggested($request->user(), 12)->map(fn ($r) => [
            'user_id' => $r['user']->id, 'reasons' => $r['reasons'],
        ]));
    }

    public function feed(Request $request, FeedService $feed)
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:30'], 'page' => ['nullable', 'integer', 'min:1']]);

        return response()->json($feed->feed($request->user(), (int) $request->input('per_page', 15), (int) $request->input('page', 1)));
    }

    public function reactPost(Request $request, Post $post, ReactionService $reactions)
    {
        $request->validate(['type' => ['nullable', 'string', 'max:20']]);

        return response()->json($reactions->togglePost($request->user(), $post, (string) $request->input('type', 'like')));
    }

    public function reactComment(Request $request, Comment $comment, ReactionService $reactions)
    {
        $request->validate(['type' => ['nullable', 'string', 'max:20']]);

        return response()->json($reactions->toggleComment($request->user(), $comment, (string) $request->input('type', 'like')));
    }

    public function stories(Request $request, StoryService $stories)
    {
        return response()->json($stories->tray($request->user(), 30));
    }

    public function showStory(Request $request, Story $story, StoryService $stories)
    {
        $this->authorize('view', $story);
        $stories->markViewed($request->user(), $story);

        return response()->json($story->fresh());
    }

    public function search(Request $request, SearchService $search)
    {
        $request->validate(['q' => ['required', 'string', 'max:80']]);

        return response()->json($search->search($request->user(), $request->string('q'), 10));
    }

    public function groups(Request $request)
    {
        return response()->json(Group::visibleTo($request->user())->withCount('members')->orderByDesc('members_count')->paginate(15));
    }

    public function recommendations(Request $request, SocialDatingRecommendationService $recommend)
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:20']]);

        return response()->json($recommend->recommend($request->user(), [], (int) $request->input('limit', 10))->map(fn ($r) => [
            'user_id' => $r['user']->id,
            'compatibility' => $r['compatibility'],
            'social_score' => $r['social_score'],
            'reasons' => $r['reasons'],
        ]));
    }
}
