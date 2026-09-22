<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Like extends Model
{
    use HasFactory;

    protected $fillable = ['liker_id', 'liked_id', 'is_super'];

    protected function casts(): array
    {
        return ['is_super' => 'boolean'];
    }

    public function liker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liker_id');
    }

    public function liked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'liked_id');
    }

    /**
     * Record a like and create a canonical UserMatch when mutual.
     */
    public static function give(User $liker, User $liked, bool $isSuper = false): Like
    {
        $like = static::firstOrCreate(
            ['liker_id' => $liker->id, 'liked_id' => $liked->id],
            ['is_super' => $isSuper]
        );

        static::createsMatch($liker, $liked, $like);

        return $like;
    }

    public static function createsMatch(User $liker, User $liked, ?Like $like = null): ?UserMatch
    {
        $mutual = static::where('liker_id', $liked->id)->where('liked_id', $liker->id)->exists();

        if (! $mutual) {
            return null;
        }

        [$a, $b] = UserMatch::canonical($liker->id, $liked->id);

        $existing = UserMatch::where('user_a_id', $a)->where('user_b_id', $b)->first();
        if ($existing) {
            // Reactivate dead match (unmatch/pass) instead of returning a dead row.
            if (! $existing->is_active) {
                $existing->update([
                    'is_active' => true,
                    'matched_at' => now(),
                    'unmatched_at' => null,
                    'like_id' => $like?->id ?? $existing->like_id,
                ]);
                $existing->wasRecentlyCreated = true;
            }

            return $existing;
        }

        return UserMatch::create([
            'user_a_id' => $a, 'user_b_id' => $b,
            'like_id' => $like?->id, 'matched_at' => now(), 'is_active' => true,
        ]);
    }

    public function isMutual(): bool
    {
        return static::where('liker_id', $this->liked_id)->where('liked_id', $this->liker_id)->exists();
    }
}
