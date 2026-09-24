<?php

namespace App\Observers;

use App\Models\Hashtag;
use App\Models\Post;
use App\Services\HashtagService;

/**
 * Keeps hashtag pivot + counters in sync whenever a post body changes.
 * Mention parsing lives in MentionService (called from controllers, where
 * the author + notification context exist).
 */
class PostObserver
{
    public function saved(Post $post): void
    {
        try {
            app(HashtagService::class)->sync($post);
        } catch (\Throwable) {
        }
    }

    public function deleted(Post $post): void
    {
        try {
            $ids = $post->hashtags()->pluck('hashtags.id')->all();
            $post->hashtags()->detach();
            foreach ($ids as $id) {
                Hashtag::where('id', $id)->update(['posts_count' => Post::whereHas('hashtags', fn ($q) => $q->where('hashtags.id', $id))->where('is_hidden', false)->count()]);
            }
        } catch (\Throwable) {
        }
    }
}
