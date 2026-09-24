<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Event;
use App\Models\Group;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;

/**
 * Global search over people/posts/communities/events/hashtags.
 * Privacy-, block-, mute- and moderation-aware. MySQL-backed (LIKE +
 * indexes); Scout/Meilisearch remains the flip-over for ~100k users.
 */
class SearchService
{
    public function __construct(protected FollowService $follows) {}

    /** @return array{people:mixed, posts:mixed, groups:mixed, events:mixed, hashtags:mixed} */
    public function search(?User $viewer, string $query, int $perPage = 10): array
    {
        $query = mb_substr(trim($query), 0, 80);
        if ($query === '') {
            return ['people' => [], 'posts' => [], 'groups' => [], 'events' => [], 'hashtags' => []];
        }
        $like = '%'.$query.'%';
        $blocked = $viewer ? array_unique(array_merge(
            Block::where('blocker_id', $viewer->id)->pluck('blocked_id')->all(),
            Block::where('blocked_id', $viewer->id)->pluck('blocker_id')->all(),
        )) : [];
        $muted = $viewer ? $this->follows->mutedIds($viewer) : [];

        $people = User::query()->active()->real()
            ->whereNotIn('users.id', array_merge($blocked, $muted, $viewer ? [$viewer->id] : [0]) ?: [0])
            ->whereDoesntHave('counselor')
            ->whereDoesntHave('profilePrivacy', fn ($q) => $q->where('is_incognito', true))
            ->where(fn ($q) => $q->where('display_name', 'like', $like)->orWhere('username', 'like', $like)->orWhere('city', 'like', $like))
            ->with(['profile'])->limit($perPage)->get();

        $posts = Post::visibleTo($viewer)
            ->whereNotIn('user_id', array_merge($blocked, $muted) ?: [0])
            ->where('body', 'like', $like)
            ->with(['user:id,display_name,name,avatar_path,is_verified'])->withCount(['comments', 'likes'])
            ->latest('id')->limit($perPage)->get();

        $groups = Group::visibleTo($viewer)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like))
            ->limit($perPage)->get();

        $events = Event::where('status', 'published')
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('city', 'like', $like))
            ->orderBy('starts_at')->limit($perPage)->get();

        $hashtags = Hashtag::where('slug', 'like', mb_strtolower(ltrim($query, '#')).'%')
            ->orderByDesc('posts_count')->limit($perPage)->get();

        try {
            app(ReactionService::class)->prime($posts, $viewer);
        } catch (\Throwable) {
        }

        return compact('people', 'posts', 'groups', 'events', 'hashtags');
    }
}
