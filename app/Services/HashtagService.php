<?php

namespace App\Services;

use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hashtags are extracted from post bodies on save (PostObserver) and
 * synced to the pivot with denormalized counters. Lookup tables are
 * cached briefly; writes stay transactional.
 */
class HashtagService
{
    public static function extract(string $body): array
    {
        preg_match_all('/#([\p{L}\p{N}_-]{2,60})/u', $body, $m);
        $out = [];
        foreach ($m[1] ?? [] as $raw) {
            $slug = Hashtag::normalize($raw);
            if ($slug !== '') {
                $out[$slug] = $raw;
            }
        }

        return $out;
    }

    public function sync(Post $post): void
    {
        $tags = self::extract((string) $post->body);
        DB::transaction(function () use ($post, $tags) {
            $ids = [];
            foreach ($tags as $slug => $name) {
                $tag = Hashtag::firstOrCreate(['slug' => $slug], ['name' => '#'.$name]);
                $ids[] = $tag->id;
            }
            $post->hashtags()->sync($ids);
            foreach ($ids as $id) {
                Hashtag::where('id', $id)->update(['posts_count' => Post::whereHas('hashtags', fn ($q) => $q->where('hashtags.id', $id))->where('is_hidden', false)->count()]);
            }
        });
    }

    /** @return array<int, array{slug:string,name:string,posts_count:int}> */
    public function trending(int $limit = 10): array
    {
        return Cache::remember('social:trending-tags', 3600, fn () => Hashtag::orderByDesc('posts_count')->limit($limit)
            ->get(['slug', 'name', 'posts_count'])->toArray());
    }
}
