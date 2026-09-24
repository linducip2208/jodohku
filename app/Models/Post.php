<?php

namespace App\Models;

use App\Enums\PrivacyVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'group_id', 'body', 'media_paths',
        'visibility', 'likes_count', 'comments_count', 'shares_count',
        'boosted_until', 'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'media_paths' => 'array',
            'visibility' => PrivacyVisibility::class,
            'is_hidden' => 'boolean',
            'boosted_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(PostBookmark::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(PostShare::class);
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'post_hashtag')->withTimestamps();
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }

    public function likedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * Visibility scope for feeds/search. Staff bypass; authors always see
     * own posts. MatchesOnly requires an active match with the author.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return $query->where('is_hidden', false)->where(function ($q) use ($viewer) {
            $q->where('visibility', PrivacyVisibility::Public);
            if ($viewer) {
                $q->orWhere('visibility', PrivacyVisibility::MembersOnly);
                if ($viewer->isPremium()) {
                    $q->orWhere('visibility', PrivacyVisibility::PremiumOnly);
                }
                $q->orWhere('user_id', $viewer->id);
                $q->orWhere(function ($qq) use ($viewer) {
                    $qq->where('visibility', PrivacyVisibility::MatchesOnly)
                        ->whereIn('user_id', UserMatch::where(fn ($m) => $m->where('user_a_id', $viewer->id)->orWhere('user_b_id', $viewer->id))
                            ->where('is_active', true)->get()->map(fn ($m) => (int) $m->user_a_id === (int) $viewer->id ? (int) $m->user_b_id : (int) $m->user_a_id)->all() ?: [0]);
                });
                if ($viewer->isStaff()) {
                    $q->orWhereIn('visibility', [PrivacyVisibility::Private, PrivacyVisibility::Hidden]);
                }
            }
        });
    }
}
