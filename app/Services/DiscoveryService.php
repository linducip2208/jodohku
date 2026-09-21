<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Boost;
use App\Models\Like;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class DiscoveryService
{
    public function __construct(protected MatchingEngine $engine) {}

    /**
     * Filtered discovery with boost-aware presentation priority.
     * Boost NEVER alters compatibility score — only ordering.
     */
    public function discover(User $user, array $filters = [], int $perPage = 20, ?string $cursor = null): CursorPaginator
    {
        $sort = $filters['sort'] ?? 'compatibility';
        $limit = min(100, max(5, $perPage * 4));

        $query = User::query()->active()->where('id', '!=', $user->id);

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }
        if (! empty($filters['education'])) {
            $query->whereHas('profile', fn ($q) => $q->where('education', 'like', '%'.$filters['education'].'%'));
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
        if (! empty($filters['min_age'])) {
            $query->where('date_of_birth', '<=', now()->subYears((int) $filters['min_age'])->toDateString());
        }
        if (! empty($filters['max_age'])) {
            $query->where('date_of_birth', '>=', now()->subYears((int) $filters['max_age'] + 1)->toDateString());
        }
        // distance filter via bounding box when coords available
        if (! empty($filters['max_distance_km']) && $user->latitude !== null) {
            $km = (int) $filters['max_distance_km'];
            $deg = $km / 111.0;
            $query->whereBetween('latitude', [(float) $user->latitude - $deg, (float) $user->latitude + $deg])
                ->whereBetween('longitude', [(float) $user->longitude - $deg, (float) $user->longitude + $deg]);
        }

        $blockedIds = Block::where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(Block::where('blocked_id', $user->id)->pluck('blocker_id'))->all();
        // Exclude already liked (unless rewound)
        $likedIds = Like::where('liker_id', $user->id)->pluck('liked_id')->all();
        $excluded = array_unique(array_merge($blockedIds, $likedIds));
        if ($excluded) {
            $query->whereNotIn('users.id', $excluded);
        }

        // Base ordering by sort mode (pre-score); final compatibility sort applied in memory
        match ($sort) {
            'distance' => $query->orderBy('last_active_at', 'desc'),
            'active' => $query->orderByDesc('last_active_at'),
            'newest' => $query->orderByDesc('users.created_at'),
            'popularity' => $query->orderByDesc('is_premium')->orderByDesc('is_verified'),
            default => $query->orderByDesc('last_active_at'),
        };

        $pool = $query->with(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers'])
            ->cursorPaginate($limit, ['*'], 'cursor', $cursor ? \Illuminate\Pagination\Cursor::fromEncoded($cursor) : null);

        // Score in memory
        $scored = $pool->getCollection()->map(function (User $cand) use ($user) {
            if (! $this->engine->passesHardFilter($user, $cand)) {
                return null;
            }
            $r = $this->engine->scorePair($user, $cand);
            $cand->setAttribute('compatibility_score', $r['mutual']);
            $cand->setAttribute('match_breakdown', $r['breakdown']);

            return $cand;
        })->filter()->values();

        // Boost-aware presentation priority (does not change score)
        $liveBoostUserIds = Boost::live()->pluck('user_id')->flip()->all();
        $scored = $scored->sortByDesc(function (User $cand) use ($sort, $liveBoostUserIds) {
            $boost = isset($liveBoostUserIds[$cand->id]) ? 1 : 0;
            // Boost adds presentation priority via tuple: boosted first, then sort key
            return [$boost, $sort === 'newest'
                ? strtotime((string) $cand->created_at)
                : (float) ($cand->compatibility_score ?? 0)];
        })->values()->take($perPage);

        return new CursorPaginator($scored, $perPage, $pool->nextCursor(), ['path' => request()->url(), 'query' => request()->query()]);
    }

    public function distanceKm(User $a, User $b): ?float
    {
        if ($a->latitude === null || $b->latitude === null) {
            return null;
        }
        $r = 6371;
        $dLat = deg2rad((float) $b->latitude - (float) $a->latitude);
        $dLon = deg2rad((float) $b->longitude - (float) $a->longitude);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad((float) $a->latitude)) * cos(deg2rad((float) $b->latitude)) * sin($dLon / 2) ** 2;

        return round(2 * $r * asin(min(1, sqrt($h))), 2);
    }
}
