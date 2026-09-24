<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Follow;
use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Support\Collection;

/**
 * Ephemeral stories (default 24h). Expiration is enforced by the
 * active() scope + expires_at index — no polling cron. A nightly prune
 * (PruneStaleData) deletes long-expired rows with their views/reactions.
 */
class StoryService
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function create(User $author, array $data): Story
    {
        $story = Story::create([
            'user_id' => $author->id,
            'type' => $data['type'] ?? 'text',
            'media_path' => $data['media_path'] ?? null,
            'body' => isset($data['body']) ? mb_substr((string) $data['body'], 0, 500) : null,
            'visibility' => $data['visibility'] ?? 'public',
            'expires_at' => now()->addHours(max(1, min(72, (int) ($data['ttl_hours'] ?? Story::DEFAULT_TTL_HOURS)))),
        ]);
        $this->analytics->capture($author, 'story_created', $story);

        return $story;
    }

    /**
     * Story tray: followed + mutuals first, then active members.
     * Respects blocks (both ways), mutes, incognito authors, counselors,
     * and per-author stories_visibility (matches_only needs a match).
     */
    public function tray(User $viewer, int $limit = 20): Collection
    {
        $blocked = Block::where('blocker_id', $viewer->id)->pluck('blocked_id')
            ->merge(Block::where('blocked_id', $viewer->id)->pluck('blocker_id'))->all();
        $muted = app(FollowService::class)->mutedIds($viewer);
        $excluded = array_unique(array_merge($blocked, $muted, [$viewer->id]));

        $followedIds = Follow::where('follower_id', $viewer->id)->pluck('followed_id')->all();

        $stories = Story::active()->whereNotIn('user_id', $excluded ?: [0])
            ->whereHas('user', fn ($q) => $q->active()->real()
                ->whereDoesntHave('counselor')
                ->whereDoesntHave('profilePrivacy', fn ($qq) => $qq->where('is_incognito', true)))
            ->with(['user:id,display_name,name,avatar_path,is_verified'])
            ->latest('id')->limit(120)->get();

        $matchedIds = $this->matchedIds($viewer);

        $visible = $stories->filter(function (Story $s) use ($viewer, $matchedIds) {
            $vis = 'public';
            try {
                $vis = $s->user->profilePrivacy?->stories_visibility?->value ?? 'public';
            } catch (\Throwable) {
            }
            if ($vis === 'public') {
                return true;
            }
            if ($vis === 'members_only') {
                return true; // viewer is a member by definition here
            }
            if ($vis === 'matches_only') {
                return in_array((int) $s->user_id, $matchedIds, true);
            }

            return (int) $s->user_id === (int) $viewer->id;
        });

        return $visible->sortByDesc(fn (Story $s) => in_array((int) $s->user_id, $followedIds, true) ? 1 : 0)
            ->take($limit)->values();
    }

    public function markViewed(User $viewer, Story $story): bool
    {
        if ((int) $story->user_id === (int) $viewer->id) {
            return false;
        }
        $created = StoryView::firstOrCreate(['story_id' => $story->id, 'user_id' => $viewer->id])->wasRecentlyCreated;
        if ($created) {
            $story->increment('views_count');
            $this->analytics->capture($viewer, 'story_view', $story);
        }

        return $created;
    }

    protected function matchedIds(User $viewer): array
    {
        try {
            return UserMatch::where(fn ($q) => $q->where('user_a_id', $viewer->id)->orWhere('user_b_id', $viewer->id))
                ->where('is_active', true)->get()->map(fn ($m) => (int) $m->user_a_id === (int) $viewer->id ? (int) $m->user_b_id : (int) $m->user_a_id)->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
