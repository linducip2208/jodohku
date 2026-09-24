<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single canonical candidate pool builder for discovery AND matchmaking.
 *
 * `DiscoveryService::poolQuery()` and `MatchingEngine::candidatesFor()`
 * historically maintained two near-identical filter stacks; both now
 * delegate here. Semantics preserved exactly:
 *
 * - Base: active users, never self.
 * - Hard filters pushed into indexed queries (gender/city/age/verified/
 *   online/premium/photo/keyword/goal/height/marital/religion/distance).
 * - Privacy: blocked (both directions) always excluded; incognito hidden
 *   unless they already liked the viewer; inactive never included.
 * - Reports do NOT exclude (preserved current behavior: only blocks gate).
 * - `excludeLiked=true` only for interactive discovery (liked profiles stay
 *   hidden until rewound); matchmaking never excludes by likes.
 * - Preference defaults (gender/age) apply only when the caller opts in
 *   (matchmaking/daily picks), never for explicit discovery filters.
 * - Ordering + unique `users.id` tiebreaker keep pool cursors stable.
 *
 * Returns a Builder: callers add eager loads, limits, and pagination.
 * No scoring here — `MatchingEngine` remains the sole scoring authority.
 */
class CandidateRetrievalService
{
    /**
     * @param  array<string,mixed>  $filters
     * @param  array{excludeLiked?:bool, preferenceDefaults?:bool}  $options
     */
    public function pool(User $user, array $filters = [], ?string $sort = null, array $options = []): Builder
    {
        $excludeLiked = (bool) ($options['excludeLiked'] ?? false);
        $defaults = (bool) ($options['preferenceDefaults'] ?? false);

        $query = User::query()->active()->where('id', '!=', $user->id);

        $this->applyAttributeFilters($query, $user, $filters, $defaults);
        $this->applyAgeFilters($query, $user, $filters, $defaults);
        $this->applyDistanceFilter($query, $user, $filters);
        $this->applyExclusions($query, $user, $filters, $excludeLiked);
        $this->applyIncognitoRule($query, $user);
        if ($sort !== null) {
            $this->applyOrdering($query, $sort);
        }

        return $query;
    }

    /** @param array<string,mixed> $filters */
    protected function applyAttributeFilters(Builder $query, User $user, array $filters, bool $defaults): void
    {
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        } elseif ($defaults && $user->partnerPreference?->gender_preference) {
            $gp = $user->partnerPreference->gender_preference;
            $query->where('gender', $gp instanceof \BackedEnum ? $gp->value : (string) $gp);
        }
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }
        if (! empty($filters['education'])) {
            $query->whereHas('profile', fn ($q) => $q->where('education', 'like', '%'.$filters['education'].'%'));
        }
        if (! empty($filters['religion'])) {
            $query->whereHas('profile', fn ($q) => $q->where('religion', $filters['religion']));
        }
        if (! empty($filters['marital_status'])) {
            $query->whereHas('profile', fn ($q) => $q->where('marital_status', $filters['marital_status']));
        }
        if (! empty($filters['relationship_goal'])) {
            $query->whereHas('profile', fn ($q) => $q->where('relationship_goal', $filters['relationship_goal']));
        }
        if (! empty($filters['height_min'])) {
            $query->whereHas('profile', fn ($q) => $q->where('height_cm', '>=', (int) $filters['height_min']));
        }
        if (! empty($filters['height_max'])) {
            $query->whereHas('profile', fn ($q) => $q->where('height_cm', '<=', (int) $filters['height_max']));
        }
        if (! empty($filters['has_photo']) && in_array($filters['has_photo'], ['1', 'true', true], true)) {
            $query->whereHas('photos', fn ($q) => $q->where('status', 'approved'));
        }
        if (! empty($filters['keyword'])) {
            $kw = (string) $filters['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('display_name', 'like', "%{$kw}%")
                    ->orWhere('username', 'like', "%{$kw}%")
                    ->orWhere('city', 'like', "%{$kw}%")
                    ->orWhereHas('profile', fn ($p) => $p->where('headline', 'like', "%{$kw}%")
                        ->orWhere('bio', 'like', "%{$kw}%")
                        ->orWhere('occupation', 'like', "%{$kw}%")
                        ->orWhere('education', 'like', "%{$kw}%"));
            });
        }
        if (! empty($filters['verified'])) {
            $query->where('is_verified', true);
        }
        if (! empty($filters['online'])) {
            $query->where('is_online', true);
        }
        if (! empty($filters['premium'])) {
            $query->where('is_premium', true);
        }
    }

    /** @param array<string,mixed> $filters */
    protected function applyAgeFilters(Builder $query, User $user, array $filters, bool $defaults): void
    {
        $minAge = $filters['min_age'] ?? ($defaults ? $user->partnerPreference?->min_age : null);
        $maxAge = $filters['max_age'] ?? ($defaults ? $user->partnerPreference?->max_age : null);
        if ($minAge) {
            $query->where('date_of_birth', '<=', now()->subYears((int) $minAge)->toDateString());
        }
        if ($maxAge) {
            // Strict lower bound: someone who already turned max_age+1 is out.
            $query->where('date_of_birth', '>', now()->subYears((int) $maxAge + 1)->toDateString());
        }
    }

    /** @param array<string,mixed> $filters */
    protected function applyDistanceFilter(Builder $query, User $user, array $filters): void
    {
        if (! empty($filters['max_distance_km']) && $user->latitude !== null) {
            $km = max(1, (int) $filters['max_distance_km']);
            $deg = $km / 111.0;
            $query->whereBetween('latitude', [(float) $user->latitude - $deg, (float) $user->latitude + $deg])
                ->whereBetween('longitude', [(float) $user->longitude - $deg, (float) $user->longitude + $deg]);
        }
    }

    /** @param array<string,mixed> $filters */
    protected function applyExclusions(Builder $query, User $user, array $filters, bool $excludeLiked): void
    {
        $blockedIds = Block::where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(Block::where('blocked_id', $user->id)->pluck('blocker_id'))->all();
        $excluded = $blockedIds;
        if ($excludeLiked) {
            // Exclude already liked (unless rewound).
            $excluded = array_merge($excluded, Like::where('liker_id', $user->id)->pluck('liked_id')->all());
        }
        $excluded = array_unique($excluded);
        if ($excluded) {
            $query->whereNotIn('users.id', $excluded);
        }
        if (! empty($filters['exclude_ids'])) {
            $query->whereNotIn('id', (array) $filters['exclude_ids']);
        }
    }

    protected function applyIncognitoRule(Builder $query, User $user): void
    {
        // Incognito: hidden from discovery unless they already liked the viewer.
        $query->where(function ($q) use ($user) {
            $q->whereDoesntHave('profilePrivacy', fn ($p) => $p->where('is_incognito', true))
                ->orWhereIn('users.id', Like::where('liked_id', $user->id)->select('liker_id'));
        });
    }

    protected function applyOrdering(Builder $query, string $sort): void
    {
        // Base ordering by sort mode (pre-score); final sort applied in memory.
        // Unique id tiebreaker keeps pool cursors stable (no skipped rows).
        match ($sort) {
            'distance' => $query->orderBy('last_active_at', 'desc'),
            'active' => $query->orderByDesc('last_active_at'),
            'newest' => $query->orderByDesc('users.created_at'),
            'popularity' => $query->orderByDesc('is_premium')->orderByDesc('is_verified'),
            default => $query->orderByDesc('last_active_at'),
        };
        $query->orderBy('users.id');
    }
}
