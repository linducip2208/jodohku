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

        // Incognito: hidden from discovery unless they already liked the viewer.
        $query->where(function ($q) use ($user) {
            $q->whereDoesntHave('profilePrivacy', fn ($p) => $p->where('is_incognito', true))
                ->orWhereIn('users.id', Like::where('liked_id', $user->id)->select('liker_id'));
        });

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

    /**
     * Curated daily picks: top-compatibility candidates that haven't been
     * recommended today, rotating via a per-user/day cache. Fall back to the
     * next-best candidates once the daily pool is exhausted.
     */
    public function dailyPicks(User $user, int $limit = 10): array
    {
        $limit = min(20, max(1, $limit));
        $cacheKey = 'discovery:picks:'.today()->toDateString().':'.$user->id;
        $picked = (array) \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        $filters = ['exclude_ids' => $picked];
        if ($user->partnerPreference?->gender_preference) {
            $gp = $user->partnerPreference->gender_preference;
            $filters['gender'] = $gp instanceof \BackedEnum ? $gp->value : (string) $gp;
        }

        $candidates = $this->engine->candidatesFor($user, $filters, $limit);

        // Top up with non-picked candidates when preferences are empty.
        if ($candidates->count() < $limit) {
            $extra = $this->engine->candidatesFor($user, ['exclude_ids' => array_merge($picked, $candidates->pluck('id')->all())], $limit - $candidates->count() + 20);
            $candidates = $candidates->merge($extra);
        }

        $picks = $candidates->take($limit)->values()->map(function (User $cand) {
            return [
                'user_id' => $cand->id,
                'display_name' => $cand->displayName(),
                'headline' => $cand->profile?->headline,
                'city' => $cand->city,
                'age' => $cand->age(),
                'avatar_url' => $cand->avatarUrl(),
                'is_premium' => (bool) $cand->is_premium,
                'is_verified' => (bool) $cand->is_verified,
                'compatibility_score' => $cand->compatibility,
                'breakdown' => $cand->match_breakdown,
            ];
        })->all();

        $newPicks = array_values(array_unique(array_merge($picked, array_column($picks, 'user_id'))));
        \Illuminate\Support\Facades\Cache::put($cacheKey, $newPicks, now()->endOfDay());

        return $picks;
    }

    public function resetDailyPicks(User $user): void
    {
        \Illuminate\Support\Facades\Cache::forget('discovery:picks:'.today()->toDateString().':'.$user->id);
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
