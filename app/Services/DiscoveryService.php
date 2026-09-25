<?php

namespace App\Services;

use App\Models\Boost;
use App\Models\User;
use App\Pagination\ScoredCursorPaginator;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DiscoveryService
{
    public function __construct(
        protected MatchingEngine $engine,
        protected CandidateRetrievalService $retrieval,
    ) {}

    /**
     * Filtered discovery with boost-aware presentation priority.
     * Boost NEVER alters compatibility score — only ordering.
     *
     * Pagination contract: the pool is fetched page-by-page and scored in
     * memory until the page is full (or the pool is exhausted, or the
     * per-request fetch cap is reached). Unshown scored rows travel in the
     * next-page cursor (lossless), and the pool cursor always advances past
     * consumed rows (no duplicates, no phantom next pages).
     */
    public function discover(User $user, array $filters = [], int $perPage = 20, ?string $cursor = null): ScoredCursorPaginator
    {
        $perPage = max(1, min(50, $perPage));
        $sort = $filters['sort'] ?? 'compatibility';
        $limit = min(100, max(5, $perPage * 4));

        $baseQuery = fn () => $this->poolQuery($user, $filters, $sort);

        // Decode incoming page cursor: ours carries pool position + leftovers,
        // anything else is treated as a legacy pool cursor (backward compat).
        $poolCursor = null;
        $leftoverIds = [];
        if ($cursor) {
            try {
                $incoming = Cursor::fromEncoded($cursor);
                try {
                    $payload = json_decode(base64_decode(strtr((string) $incoming->parameter('jk'), '-_', '+/')), true);
                    if (is_array($payload)) {
                        $leftoverIds = array_values(array_filter(array_map('intval', (array) ($payload['left'] ?? []))));
                        $poolCursor = ! empty($payload['pool']) ? Cursor::fromEncoded($payload['pool']) : null;
                    }
                } catch (\Throwable) {
                    $poolCursor = $incoming;
                }
            } catch (\Throwable) {
                $poolCursor = null;
            }
        }

        $scored = collect();
        $blockedIds = $this->engine->blockedIdsFor($user);
        // Leftover rows from the previous page come first (already filtered).
        if ($leftoverIds) {
            $preloaded = User::whereIn('id', array_slice($leftoverIds, 0, $perPage * 2))
                ->with(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers'])
                ->withCount(['photos as approved_photos_count' => fn ($q) => $q->where('status', 'approved')])
                ->get()->filter(fn (User $cand) => $this->engine->passesHardFilterFast($user, $cand, $blockedIds))->values();
            if ($preloaded->isNotEmpty()) {
                foreach ($this->engine->scoreMany($user, $preloaded) as $id => $r) {
                    $cand = $preloaded->firstWhere('id', $id);
                    if ($cand) {
                        $cand->setAttribute('compatibility_score', $r['mutual']);
                        $cand->setAttribute('match_breakdown', $r['breakdown']);
                        $scored->push($cand);
                    }
                }
            }
        }

        $poolNext = null;
        // Fetch pool pages until the page is full. First iteration always
        // runs when there is no pool position yet (initial page); later
        // iterations only when the pool has more rows to offer.
        for ($page = 0; $page < 3 && $scored->count() < $perPage && ($page === 0 ? ($poolCursor !== null || $leftoverIds === []) : $poolNext !== null); $page++) {
            $pool = $baseQuery()
                ->with(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers'])
                ->withCount(['photos as approved_photos_count' => fn ($q) => $q->where('status', 'approved')])
                ->cursorPaginate($limit, ['*'], 'cursor', $poolCursor);
            $poolNext = $pool->nextCursor();
            $batch = $pool->getCollection()->filter(fn (User $cand) => $this->engine->passesHardFilterFast($user, $cand, $blockedIds))->values();
            if ($batch->isNotEmpty()) {
                // MatchScore read-path: fresh rows reused, missing recomputed + persisted.
                foreach ($this->engine->scoreMany($user, $batch) as $id => $r) {
                    $cand = $batch->firstWhere('id', $id);
                    if ($cand) {
                        $cand->setAttribute('compatibility_score', $r['mutual']);
                        $cand->setAttribute('match_breakdown', $r['breakdown']);
                        $scored->push($cand);
                    }
                }
            }
            if ($poolNext === null) {
                break;
            }
            $poolCursor = $poolNext;
        }

        // Boost-aware presentation priority, scoped to THIS pool (one query,
        // not global). Numeric composite key — never array returns.
        $poolIds = $scored->pluck('id')->all();
        $liveBoosted = $poolIds ? Boost::live()->whereIn('user_id', $poolIds)->pluck('user_id')->flip()->all() : [];
        $scored = $scored->sortByDesc(function (User $cand) use ($sort, $liveBoosted, $user) {
            $boost = isset($liveBoosted[$cand->id]) ? 1e12 : 0;
            $key = match ($sort) {
                'newest' => strtotime((string) $cand->created_at) ?: 0,
                'active' => ($cand->last_active_at ? strtotime((string) $cand->last_active_at) : 0),
                'distance' => ($user->latitude !== null && $cand->latitude !== null)
                    ? -$this->distanceKm($user, $cand)
                    : (float) ($cand->compatibility_score ?? 0),
                'popularity' => (($cand->is_premium ? 50 : 0) + ($cand->is_verified ? 25 : 0) + (float) ($cand->compatibility_score ?? 0) / 4),
                default => (float) ($cand->compatibility_score ?? 0) + (strtotime((string) $cand->last_active_at) ?: 0) / 1e10,
            };

            return $boost + $key;
        })->values();

        $items = $scored->take($perPage)->values();
        // P2 liquidity backfill (first page only): small cities collapse when
        // the distance bbox is too tight. If the page is short, retry with
        // 2x/3x radius (capped) excluding already-seen ids — same scoring,
        // same boost-only ranking, no cursor tricks (cursor stays null-safe
        // because backfill only runs when no pool position exists yet).
        if ($cursor === null && $items->count() < $perPage && ! empty($filters['max_distance_km']) && $user->latitude !== null && $user->longitude !== null) {
            $extra = $this->backfillByRadius($user, $filters, $sort, $perPage - $items->count(), $scored->pluck('id')->all());
            if ($extra->isNotEmpty()) {
                $combined = $scored->merge($extra);
                $poolIds = $combined->pluck('id')->all();
                $liveBoosted = $poolIds ? Boost::live()->whereIn('user_id', $poolIds)->pluck('user_id')->flip()->all() : $liveBoosted;
                $scored = $combined->sortByDesc(function (User $cand) use ($sort, $liveBoosted, $user) {
                    $boost = isset($liveBoosted[$cand->id]) ? 1e12 : 0;
                    $key = match ($sort) {
                        'newest' => strtotime((string) $cand->created_at) ?: 0,
                        'active' => ($cand->last_active_at ? strtotime((string) $cand->last_active_at) : 0),
                        'distance' => ($user->latitude !== null && $cand->latitude !== null)
                            ? -$this->distanceKm($user, $cand)
                            : (float) ($cand->compatibility_score ?? 0),
                        'popularity' => (($cand->is_premium ? 50 : 0) + ($cand->is_verified ? 25 : 0) + (float) ($cand->compatibility_score ?? 0) / 4),
                        default => (float) ($cand->compatibility_score ?? 0) + (strtotime((string) $cand->last_active_at) ?: 0) / 1e10,
                    };

                    return $boost + $key;
                })->values();
                $items = $scored->take($perPage)->values();
            }
        }
        // Unshown scored rows travel in the next-page cursor (capped tail —
        // lowest-ranked first to drop), so no candidate is ever skipped.
        $leftover = $scored->slice($perPage)->take($perPage * 2)->pluck('id')->all();
        $next = null;
        if ($leftover || $poolNext !== null) {
            $payload = base64_encode(json_encode([
                'pool' => $poolNext?->encode(),
                'left' => array_values($leftover),
            ]));
            $next = new Cursor(['jk' => rtrim(strtr($payload, '+/', '-_'), '=')], true);
        }

        $incoming = null;
        try {
            $incoming = $cursor ? Cursor::fromEncoded($cursor) : null;
        } catch (\Throwable) {
        }

        return new ScoredCursorPaginator($items, $perPage, $incoming, ['path' => request()->url(), 'query' => request()->query()], $next);
    }

    /** Shared pool query (filters + base ordering), reused per pool page. */
    protected function poolQuery(User $user, array $filters, string $sort)
    {
        // Delegates to the canonical retrieval service (same semantics:
        // liked profiles stay hidden until rewound, no preference defaults).
        return $this->retrieval->pool($user, $filters, $sort, ['excludeLiked' => true]);
    }

    /**
     * Relaxed-radius top-up for thin pools (P2/C7). Widens max_distance_km
     * stepwise, never narrows; excludes already-seen ids; scores via the
     * same MatchScore read-path. Returns scored users (may be empty).
     */
    protected function backfillByRadius(User $user, array $filters, string $sort, int $need, array $seenIds): Collection
    {
        $baseKm = max(1, (int) $filters['max_distance_km']);
        $capKm = max($baseKm * 3, (int) config('matchmaking.hard_filters.max_distance_default_km', 200), 50);
        $blockedIds = $this->engine->blockedIdsFor($user);
        $found = collect();
        $excluded = $seenIds;

        foreach ([2, 3] as $mult) {
            if ($found->count() >= $need) {
                break;
            }
            $km = min($baseKm * $mult, $capKm);
            if ($km <= $baseKm) {
                continue;
            }
            $wide = array_merge($filters, ['max_distance_km' => $km, 'exclude_ids' => $excluded]);
            try {
                $batch = $this->retrieval->pool($user, $wide, $sort, ['excludeLiked' => true])
                    ->with(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers'])
                    ->withCount(['photos as approved_photos_count' => fn ($q) => $q->where('status', 'approved')])
                    ->limit(max(20, ($need - $found->count()) * 4))
                    ->get()
                    ->filter(fn (User $cand) => $this->engine->passesHardFilterFast($user, $cand, $blockedIds))
                    ->values();
            } catch (\Throwable) {
                break;
            }
            if ($batch->isEmpty()) {
                continue;
            }
            try {
                foreach ($this->engine->scoreMany($user, $batch) as $id => $r) {
                    $cand = $batch->firstWhere('id', $id);
                    if ($cand) {
                        $cand->setAttribute('compatibility_score', $r['mutual']);
                        $cand->setAttribute('match_breakdown', $r['breakdown']);
                        $found->push($cand);
                        $excluded[] = $cand->id;
                    }
                }
            } catch (\Throwable) {
                break;
            }
        }

        return $found->take($need)->values();
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
        $picked = (array) Cache::get($cacheKey, []);

        $filters = ['exclude_ids' => $picked];
        $pref = $user->partnerPreference;
        if ($pref?->gender_preference) {
            $gp = $pref->gender_preference;
            $filters['gender'] = $gp instanceof \BackedEnum ? $gp->value : (string) $gp;
        }
        // Honor the member's own age/distance preferences in daily picks.
        if ($pref?->min_age) {
            $filters['min_age'] = (int) $pref->min_age;
        }
        if ($pref?->max_age) {
            $filters['max_age'] = (int) $pref->max_age;
        }
        if ($pref?->max_distance_km && $user->latitude !== null) {
            $filters['max_distance_km'] = (int) $pref->max_distance_km;
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
        Cache::put($cacheKey, $newPicks, now()->endOfDay());

        return $picks;
    }

    public function resetDailyPicks(User $user): void
    {
        Cache::forget('discovery:picks:'.today()->toDateString().':'.$user->id);
    }

    public function distanceKm(User $a, User $b): ?float
    {
        // Passport-aware for the viewer side; candidates always use real coords.
        // Privacy: distances are fuzzed to 0.5km steps (never exact GPS).
        $passport = app(PassportService::class);
        $la = $passport->effectiveLocation($a);
        if ($la['latitude'] === null || $b->latitude === null) {
            return null;
        }
        $aLat = $la['latitude'];
        $aLng = $la['longitude'];
        $r = 6371;
        $dLat = deg2rad((float) $b->latitude - (float) $aLat);
        $dLon = deg2rad((float) $b->longitude - (float) $aLng);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad((float) $aLat)) * cos(deg2rad((float) $b->latitude)) * sin($dLon / 2) ** 2;
        $raw = 2 * $r * asin(min(1, sqrt($h)));

        return max(0.5, round($raw * 2) / 2);
    }
}
