<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Canonical match between two users.
 *
 * Named UserMatch (table: matches) because `Match` is a reserved
 * keyword in PHP 8 and cannot be used as a class name.
 */
class UserMatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'matches';

    protected $fillable = [
        'ulid', 'user_a_id', 'user_b_id', 'like_id',
        'compatibility_score', 'is_active', 'matched_at', 'unmatched_at',
    ];

    protected function casts(): array
    {
        return [
            'compatibility_score' => 'decimal:2',
            'is_active' => 'boolean',
            'matched_at' => 'datetime',
            'unmatched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UserMatch $match) {
            $match->ulid ??= (string) Str::ulid();
            [$a, $b] = static::canonical($match->user_a_id, $match->user_b_id);
            $match->user_a_id = $a;
            $match->user_b_id = $b;
        });
    }

    /** Canonical ordering so (1,2) and (2,1) map to the same pair. @return array{int,int} */
    public static function canonical(int|string $userA, int|string $userB): array
    {
        $a = (int) $userA;
        $b = (int) $userB;

        return $a <= $b ? [$a, $b] : [$b, $a];
    }

    public static function forPair(int $userA, int $userB): ?self
    {
        [$a, $b] = static::canonical($userA, $userB);

        return static::where('user_a_id', $a)->where('user_b_id', $b)->first();
    }

    /** @param Builder<UserMatch> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('unmatched_at');
    }

    /** @param Builder<UserMatch> $query */
    public function scopeInvolving(Builder $query, int $userId): Builder
    {
        return $query->where(fn ($q) => $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId));
    }

    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function like(): BelongsTo
    {
        return $this->belongsTo(Like::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'match_id');
    }

    public function otherUserId(int $userId): int
    {
        return $this->user_a_id === $userId ? $this->user_b_id : $this->user_a_id;
    }

    public function unmatch(): bool
    {
        return $this->update(['is_active' => false, 'unmatched_at' => now()]);
    }
}
