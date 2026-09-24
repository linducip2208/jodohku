<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Post;
use App\Models\PostBookmark;
use App\Models\PostLike;
use App\Models\PostReaction;
use App\Models\Story;
use App\Models\StoryReaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extensible reactions (like/love/haha/wow/support/interesting).
 * New types need no schema change — just extend TYPES. Legacy PostLike
 * rows are preserved and counted alongside type=like.
 */
class ReactionService
{
    public const TYPES = ['like', 'love', 'haha', 'wow', 'support', 'interesting'];

    public const MAX_TYPES_PER_TARGET = 1;

    public function __construct(protected AnalyticsService $analytics) {}

    public static function validType(string $type): bool
    {
        return in_array(mb_strtolower($type), self::TYPES, true);
    }

    protected function guard(User $user, User $author): void
    {
        if (Block::existsBetween((int) $user->id, (int) $author->id)) {
            throw new \RuntimeException('Interaction blocked.');
        }
    }

    /** Toggle one reaction type per target (idempotent both ways). */
    public function togglePost(User $user, Post $post, string $type = 'like'): array
    {
        $type = mb_strtolower($type);
        if (! self::validType($type)) {
            throw new \InvalidArgumentException('Unknown reaction type.');
        }
        $this->guard($user, $post->user()->withTrashed()->first() ?? $user);

        return DB::transaction(function () use ($user, $post, $type) {
            $existing = PostReaction::where('post_id', $post->id)->where('user_id', $user->id)->first();
            if ($existing && $existing->type === $type) {
                $existing->delete();
                // Legacy like row mirrors the toggle for backward compat.
                if ($type === 'like') {
                    PostLike::where('post_id', $post->id)->where('user_id', $user->id)->delete();
                }
                $this->syncPostLikeCount($post);

                return ['active' => false, 'type' => $type, 'counts' => $this->postCounts($post)];
            }
            // One type per user per post: replace, don't stack.
            PostReaction::where('post_id', $post->id)->where('user_id', $user->id)->delete();
            PostLike::where('post_id', $post->id)->where('user_id', $user->id)->delete();
            PostReaction::create(['post_id' => $post->id, 'user_id' => $user->id, 'type' => $type]);
            if ($type === 'like') {
                PostLike::firstOrCreate(['post_id' => $post->id, 'user_id' => $user->id]);
            }
            $this->syncPostLikeCount($post);
            $this->analytics->capture($user, 'post_reaction', $post, ['type' => $type]);

            return ['active' => true, 'type' => $type, 'counts' => $this->postCounts($post)];
        });
    }

    public function toggleComment(User $user, Comment $comment, string $type = 'like'): array
    {
        $type = mb_strtolower($type);
        if (! self::validType($type)) {
            throw new \InvalidArgumentException('Unknown reaction type.');
        }
        $this->guard($user, $comment->user()->withTrashed()->first() ?? $user);

        return DB::transaction(function () use ($user, $comment, $type) {
            $existing = CommentReaction::where('comment_id', $comment->id)->where('user_id', $user->id)->first();
            if ($existing && $existing->type === $type) {
                $existing->delete();

                return ['active' => false, 'type' => $type];
            }
            CommentReaction::where('comment_id', $comment->id)->where('user_id', $user->id)->delete();
            CommentReaction::create(['comment_id' => $comment->id, 'user_id' => $user->id, 'type' => $type]);

            return ['active' => true, 'type' => $type];
        });
    }

    public function toggleStory(User $user, Story $story, string $type = 'like'): array
    {
        $type = mb_strtolower($type);
        if (! self::validType($type)) {
            throw new \InvalidArgumentException('Unknown reaction type.');
        }

        return DB::transaction(function () use ($user, $story, $type) {
            $existing = StoryReaction::where('story_id', $story->id)->where('user_id', $user->id)->first();
            if ($existing && $existing->type === $type) {
                $existing->delete();
                $story->decrement('reactions_count');

                return ['active' => false, 'type' => $type];
            }
            StoryReaction::where('story_id', $story->id)->where('user_id', $user->id)->delete();
            StoryReaction::create(['story_id' => $story->id, 'user_id' => $user->id, 'type' => $type]);
            $story->increment('reactions_count');
            $this->analytics->capture($user, 'story_reaction', $story, ['type' => $type]);

            return ['active' => true, 'type' => $type];
        });
    }

    /** Per-type counts incl. legacy likes under type=like. */
    public function postCounts(Post $post): array
    {
        return $this->countsFor(collect([$post]))[$post->id] ?? array_fill_keys(array_merge(self::TYPES, ['total']), 0);
    }

    /** Bulk counts for many posts in 2 queries (feed pages). */
    public function countsFor(Collection $posts): array
    {
        $ids = $posts->pluck('id')->all();
        if (! $ids) {
            return [];
        }
        $rows = PostReaction::whereIn('post_id', $ids)->selectRaw('post_id, type, COUNT(*) c')->groupBy('post_id', 'type')->get();
        $legacy = PostLike::whereIn('post_id', $ids)->selectRaw('post_id, user_id')->get()->groupBy('post_id');
        $typedLikeUsers = PostReaction::whereIn('post_id', $ids)->where('type', 'like')->selectRaw('post_id, user_id')->get()->groupBy('post_id');
        $out = [];
        foreach ($ids as $id) {
            $row = [];
            foreach (self::TYPES as $t) {
                $row[$t] = 0;
            }
            foreach ($rows->where('post_id', $id) as $r) {
                $row[$r->type] = (int) $r->c;
            }
            $typedUsers = ($typedLikeUsers[$id] ?? collect())->pluck('user_id')->all();
            $row['like'] += ($legacy[$id] ?? collect())->whereNotIn('user_id', $typedUsers)->count();
            $row['total'] = array_sum(array_intersect_key($row, array_flip(self::TYPES)));
            $out[$id] = $row;
        }

        return $out;
    }

    /**
     * Preload viewer state + reply lists onto a post collection (bulk
     * queries, page-size independent). Components read `reaction_counts` /
     * `viewer_reaction` / `viewer_saved` / `reply_list` and skip row queries.
     */
    public function prime(Collection $posts, ?User $viewer): void
    {
        if ($posts->isEmpty()) {
            return;
        }
        $counts = $this->countsFor($posts);
        $reactions = $viewer ? PostReaction::whereIn('post_id', $posts->pluck('id'))
            ->where('user_id', $viewer->id)->pluck('type', 'post_id')->all() : [];
        $saved = $viewer ? PostBookmark::whereIn('post_id', $posts->pluck('id'))
            ->where('user_id', $viewer->id)->pluck('post_id')->flip()->all() : [];
        foreach ($posts as $post) {
            $post->setAttribute('reaction_counts', $counts[$post->id] ?? null);
            $post->setAttribute('viewer_reaction', $reactions[$post->id] ?? null);
            $post->setAttribute('viewer_saved', isset($saved[$post->id]));
        }
        $commentIds = $posts->flatMap(fn ($p) => $p->relationLoaded('comments') ? $p->comments->pluck('id') : [])->unique()->values();
        if ($commentIds->isNotEmpty()) {
            $replies = Comment::whereIn('parent_id', $commentIds)->with('user:id,display_name,name')
                ->orderByDesc('id')->get()->groupBy('parent_id');
            foreach ($posts as $post) {
                if (! $post->relationLoaded('comments')) {
                    continue;
                }
                foreach ($post->comments as $comment) {
                    $comment->setAttribute('reply_list', ($replies[$comment->id] ?? collect())->take(2)->values());
                }
            }
        }
    }

    protected function syncPostLikeCount(Post $post): void
    {
        $counts = $this->postCounts($post);
        $post->update(['likes_count' => $counts['total']]);
    }
}
