<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Follow;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Home social feed. Pipeline: candidates → filtering → privacy → safety
 * → scoring → ranking → pagination. Controllers stay thin; all logic here.
 * Eager loads are fixed; candidate window is bounded (no full-table load).
 */
class FeedService
{
    public function __construct(protected AnalyticsService $analytics) {}

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    public function feed(User $user, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, min(30, $perPage));
        $blocked = Block::where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(Block::where('blocked_id', $user->id)->pluck('blocker_id'))->all();
        $muted = app(FollowService::class)->mutedIds($user);
        $excludedAuthors = array_unique(array_merge($blocked, $muted));

        $followingIds = Follow::where('follower_id', $user->id)->pluck('followed_id')->all();
        $groupIds = GroupMember::where('user_id', $user->id)->pluck('group_id')->all();
        $interestIds = $user->interests()->pluck('interests.id')->all();

        $candidates = Post::visibleTo($user)
            ->whereNotIn('user_id', $excludedAuthors ?: [0])
            ->whereDoesntHave('user.profilePrivacy', fn ($q) => $q->where('is_incognito', true))
            ->with(['user:id,display_name,name,avatar_path,is_verified,is_online', 'comments' => fn ($q) => $q->latest('id')->limit(3)->with('user:id,display_name,name'), 'hashtags'])
            ->withCount(['comments', 'likes'])
            ->latest('id')->limit(300)->get();

        $ranked = $candidates->map(function (Post $p) use ($followingIds, $groupIds, $interestIds) {
            $score = 0;
            $hours = max(1, (int) $p->created_at?->diffInHours(now()));
            $score += 100 / $hours; // recency
            $score += min(50, ((int) $p->likes_count) * 2 + ((int) $p->comments_count) * 4); // engagement
            if (in_array((int) $p->user_id, $followingIds, true)) {
                $score += 60; // following
            }
            if ($p->group_id && in_array((int) $p->group_id, $groupIds, true)) {
                $score += 45; // my communities
            }
            if ($interestIds && $p->relationLoaded('hashtags')) {
                $score += 10; // hashtag-bearing posts get interest matching below
            }
            if (! empty($p->boosted_until) && $p->boosted_until > now()) {
                $score += 80; // paid boost (labeled in UI)
            }

            return ['post' => $p, 'score' => $score];
        })->sortByDesc('score')->values();

        $total = $ranked->count();
        $items = $ranked->forPage($page, $perPage)->map(fn ($r) => $r['post'])->values();
        try {
            app(ReactionService::class)->prime($items, $user);
        } catch (\Throwable) {
        }

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    /** Trending posts: engagement velocity over 72h, same safety filters. */
    public function trending(User $user, int $limit = 10): Collection
    {
        $blocked = Block::where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(Block::where('blocked_id', $user->id)->pluck('blocker_id'))->all();

        return Post::visibleTo($user)->whereNotIn('user_id', $blocked ?: [0])
            ->where('created_at', '>=', now()->subHours(72))
            ->with(['user:id,display_name,name,avatar_path,is_verified'])
            ->withCount(['comments', 'likes'])
            ->orderByRaw('(likes_count * 2 + comments_count * 4) DESC')->limit($limit)->get();
    }
}
