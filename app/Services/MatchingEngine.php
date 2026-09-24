<?php

namespace App\Services;

use App\Models\Block;
use App\Models\MatchScore;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MatchingEngine
{
    /**
     * Per-instance memos (NOT function-statics: queue workers are
     * long-lived, so cross-request statics would serve stale blocks/
     * weights to later jobs; instances are fresh per job/request).
     *
     * @var array<int, array>
     */
    protected array $weightsMemo = [];

    /** @var array<int, array> */
    protected array $blockedIdsMemo = [];

    public function weights(): array
    {
        // Memoized per request AND version: 8 Setting queries per scorePair
        // became ~1000 queries on a dailyPicks run. Version key keeps admin
        // tuning effective immediately after the bump.
        $version = $this->weightsVersion();
        if (isset($this->weightsMemo[$version])) {
            return $this->weightsMemo[$version];
        }
        $defaults = [
            'age' => 10, 'location' => 10, 'preference' => 20, 'personality' => 20,
            'interest' => 10, 'lifestyle' => 10, 'goal' => 10, 'behavior' => 10,
        ];

        try {
            $configured = config('matchmaking.weights', $defaults);
            // Single source of truth: DB Settings (admin) override ENV config.
            foreach (array_keys($defaults) as $key) {
                $dbVal = Setting::get('match.weight.'.$key, null, 'matchmaking');
                if ($dbVal !== null && $dbVal !== '') {
                    $configured[$key] = (float) $dbVal;
                }
            }
        } catch (\Throwable) {
            $configured = $defaults;
        }

        $merged = array_merge($defaults, is_array($configured) ? $configured : []);
        $total = max(1, array_sum($merged));

        // Normalize to 100
        foreach ($merged as $k => $v) {
            $merged[$k] = round($v / $total * 100, 2);
        }

        return $this->weightsMemo[$version] = $merged;
    }

    /** Bump when admin changes weights so versioned score caches invalidate. */
    public function weightsVersion(): int
    {
        try {
            return (int) Setting::get('matchmaking.version', 1, 'matchmaking');
        } catch (\Throwable) {
            return 1;
        }
    }

    /** Canonical pair ordering for match_scores/user_matches. */
    public static function canonical(int $a, int $b): array
    {
        return $a <= $b ? [$a, $b] : [$b, $a];
    }

    public function passesHardFilter(User $a, User $b): bool
    {
        if ($a->id === $b->id) {
            return false;
        }
        if (($b->status?->value ?? 'active') !== 'active') {
            return false;
        }
        if (Block::existsBetween((int) $a->id, (int) $b->id)) {
            return false;
        }

        return true;
    }

    /** Block ids touching $user, memoized on this instance (see above). */
    public function blockedIdsFor(User $user): array
    {
        $key = (int) $user->id;
        if (! array_key_exists($key, $this->blockedIdsMemo)) {
            try {
                $this->blockedIdsMemo[$key] = Block::where('blocker_id', $key)->pluck('blocked_id')
                    ->merge(Block::where('blocked_id', $key)->pluck('blocker_id'))
                    ->map(fn ($id) => (int) $id)->all();
            } catch (\Throwable) {
                $this->blockedIdsMemo[$key] = [];
            }
        }

        return $this->blockedIdsMemo[$key];
    }

    /** passesHardFilter() with a preloaded block list (hot loops). */
    public function passesHardFilterFast(User $a, User $b, array $blockedIds): bool
    {
        if ((int) $a->id === (int) $b->id) {
            return false;
        }
        if (($b->status?->value ?? 'active') !== 'active') {
            return false;
        }
        if (in_array((int) $b->id, $blockedIds, true)) {
            return false;
        }

        return true;
    }

    public function scorePair(User $a, User $b): array
    {
        $a->loadMissing(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers']);
        $b->loadMissing(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers']);
        // Eager approved-photo counts once: preference/behavior scoring must
        // never issue per-candidate exists() queries (N+1 on discovery pools).
        // (loadCountMissing is unavailable here, so guard on the attribute.)
        foreach ([$a, $b] as $u) {
            if (! array_key_exists('approved_photos_count', $u->getAttributes())) {
                $u->loadCount(['photos as approved_photos_count' => fn ($q) => $q->where('status', 'approved')]);
            }
        }

        $parts = [
            'age' => $this->ageScore($a, $b),
            'location' => $this->locationScore($a, $b),
            'preference' => $this->preferenceScore($a, $b),
            'personality' => $this->questionnaireScore($a, $b),
            'interest' => $this->interestScore($a, $b),
            'lifestyle' => $this->lifestyleScore($a, $b),
            'goal' => $this->goalScore($a, $b),
            'behavior' => $this->behaviorScore($b),
        ];

        $partsReverse = [
            'age' => $this->ageScore($b, $a),
            'location' => $parts['location'],
            'preference' => $this->preferenceScore($b, $a),
            'personality' => $parts['personality'],
            'interest' => $parts['interest'],
            'lifestyle' => $parts['lifestyle'],
            'goal' => $parts['goal'],
            'behavior' => $this->behaviorScore($a),
        ];

        // Mutual gate: if hard gender/age preference violated both ways, dampen
        $mutualGate = $this->mutualGate($a, $b);

        $weights = $this->weights();
        $aToB = $this->finalize($parts, $weights) * $mutualGate;
        $bToA = $this->finalize($partsReverse, $weights) * $mutualGate;
        $mutual = round(($aToB + $bToA) / 2, 2);

        return [
            'a_to_b' => round($aToB, 2),
            'b_to_a' => round($bToA, 2),
            'mutual' => $mutual,
            'breakdown' => $parts,
            'breakdown_reverse' => $partsReverse,
            'weights' => $weights,
        ];
    }

    protected function finalize(array $parts, array $weights): float
    {
        $sum = 0;
        foreach ($parts as $k => $v) {
            $sum += ($v / 100) * ($weights[$k] ?? 0);
        }

        return round(min(100, max(0, $sum)), 2);
    }

    protected function ageScore(User $seeker, User $candidate): float
    {
        $age = $candidate->age();
        $pref = $seeker->partnerPreference;
        if ($age === null || ! $pref) {
            return 70.0;
        }
        $min = $pref->min_age;
        $max = $pref->max_age;
        if ($min === null && $max === null) {
            return 80.0;
        }
        if (($min === null || $age >= $min) && ($max === null || $age <= $max)) {
            // closer to middle = higher
            if ($min !== null && $max !== null && $max > $min) {
                $mid = ($min + $max) / 2;
                $dev = abs($age - $mid) / (($max - $min) / 2 + 1);

                return round(100 - $dev * 20, 2);
            }

            return 100.0;
        }
        $gap = $min !== null && $age < $min ? $min - $age : $age - ($max ?? $age);

        return max(0, 100 - $gap * 15);
    }

    protected function locationScore(User $a, User $b): float
    {
        if ($a->latitude === null || $b->latitude === null) {
            // fallback: same city bonus
            if ($a->city && $b->city) {
                return strtolower($a->city) === strtolower($b->city) ? 85.0 : 55.0;
            }

            return 60.0;
        }
        $dist = $this->haversineKm((float) $a->latitude, (float) $a->longitude, (float) $b->latitude, (float) $b->longitude);
        $max = $a->partnerPreference?->max_distance_km ?? config('matchmaking.hard_filters.max_distance_default_km', 200);
        $max = max(5, (int) $max);
        if ($dist >= $max) {
            return max(0, 100 - ($dist / $max) * 100);
        }

        return round(100 - ($dist / $max) * 70, 2);
    }

    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($h)));
    }

    protected function preferenceScore(User $seeker, User $candidate): float
    {
        $pref = $seeker->partnerPreference;
        if (! $pref) {
            return 70.0;
        }
        $scores = [];
        $weights = [];

        $candProfile = $candidate->profile;
        $rel = fn ($importance) => match ($importance?->value ?? 'neutral') {
            'required' => 3.0, 'important' => 2.0, 'preferred' => 1.0, 'neutral' => 0.5, 'avoid' => 1.0, default => 0.5,
        };

        if ($pref->religion) {
            $match = $candProfile && strtolower((string) $candProfile->religion) === strtolower($pref->religion);
            $scores[] = $match ? 100 : 20;
            $weights[] = $rel($pref->religion_importance);
        }
        if ($pref->education) {
            $match = $candProfile && stripos((string) $candProfile->education, (string) $pref->education) !== false;
            $scores[] = $match ? 100 : 40;
            $weights[] = $rel($pref->education_importance);
        }
        if ($pref->marital_status) {
            $match = $candProfile && ($candProfile->marital_status?->value ?? (string) $candProfile->marital_status) === ($pref->marital_status?->value ?? (string) $pref->marital_status);
            $scores[] = $match ? 100 : 25;
            $weights[] = $rel($pref->marital_importance);
        }
        if ($pref->gender_preference) {
            $gp = $pref->gender_preference?->value ?? (string) $pref->gender_preference;
            $cg = $candidate->gender?->value ?? (string) $candidate->gender;
            $scores[] = ($gp === $cg) ? 100 : 0;
            $weights[] = 3.0;
        }
        if ($pref->verified_only) {
            $scores[] = $candidate->is_verified ? 100 : 10;
            $weights[] = 1.5;
        }
        if ($pref->photo_only) {
            $scores[] = $this->hasApprovedPhoto($candidate) ? 100 : 20;
            $weights[] = 1.0;
        }

        if (empty($scores)) {
            return 75.0;
        }
        $wSum = array_sum($weights);
        $acc = 0;
        foreach ($scores as $i => $s) {
            $acc += $s * ($weights[$i] / $wSum);
        }

        return round($acc, 2);
    }

    protected function questionnaireScore(User $a, User $b): float
    {
        $aa = $a->questionnaireAnswers->keyBy('question_id');
        $bb = $b->questionnaireAnswers->keyBy('question_id');
        $common = $aa->keys()->intersect($bb->keys());
        if ($common->isEmpty()) {
            return 60.0;
        }
        $total = 0;
        $count = 0;
        foreach ($common as $qid) {
            $x = $aa[$qid];
            $y = $bb[$qid];
            if ($x->question_option_id && $y->question_option_id) {
                $total += $x->question_option_id === $y->question_option_id ? 100 : 30;
            } elseif ($x->answer_value !== null && $y->answer_value !== null) {
                $total += ((string) $x->answer_value === (string) $y->answer_value) ? 100 : 40;
            } elseif ($x->answer_score !== null || $y->answer_score !== null) {
                $diff = abs((int) $x->answer_score - (int) $y->answer_score);
                $total += max(0, 100 - $diff * 10);
            } else {
                $total += 50;
            }
            $count++;
        }

        return round($total / max(1, $count), 2);
    }

    protected function interestScore(User $a, User $b): float
    {
        $ia = $a->interests->pluck('id')->all();
        $ib = $b->interests->pluck('id')->all();
        if (empty($ia) || empty($ib)) {
            return 55.0;
        }
        $inter = count(array_intersect($ia, $ib));
        $union = count(array_unique(array_merge($ia, $ib)));
        if ($union === 0) {
            return 55.0;
        }

        return round($inter / $union * 100, 2);
    }

    protected function lifestyleScore(User $a, User $b): float
    {
        $pa = $a->profile;
        $pb = $b->profile;
        if (! $pa || ! $pb) {
            return 60.0;
        }
        $score = 0;
        $n = 0;
        foreach (['smoking', 'drinking'] as $f) {
            if ($pa->{$f} || $pb->{$f}) {
                $score += strtolower((string) $pa->{$f}) === strtolower((string) $pb->{$f}) ? 100 : 35;
                $n++;
            }
        }
        // height preference check
        $pref = $a->partnerPreference;
        if ($pref && $pb->height_cm) {
            $ok = true;
            if ($pref->min_height_cm && $pb->height_cm < $pref->min_height_cm) {
                $ok = false;
            }
            if ($pref->max_height_cm && $pb->height_cm > $pref->max_height_cm) {
                $ok = false;
            }
            $score += $ok ? 100 : 30;
            $n++;
        }

        return $n === 0 ? 65.0 : round($score / $n, 2);
    }

    protected function goalScore(User $a, User $b): float
    {
        $ga = $a->profile?->relationship_goal;
        $gb = $b->profile?->relationship_goal;
        $ga = $ga?->value ?? (string) $ga;
        $gb = $gb?->value ?? (string) $gb;
        if (! $ga || ! $gb) {
            return 60.0;
        }
        if ($ga === $gb) {
            return 100.0;
        }
        $compatible = [
            'marriage' => ['serious_relationship', 'dating'],
            'serious_relationship' => ['marriage', 'dating'],
            'dating' => ['serious_relationship', 'friendship'],
            'friendship' => ['dating', 'networking'],
        ];

        return in_array($gb, $compatible[$ga] ?? [], true) ? 65.0 : 25.0;
    }

    protected function behaviorScore(User $u): float
    {
        $score = 60.0;
        if ($u->is_online) {
            $score += 15;
        } elseif ($u->last_active_at && $u->last_active_at->gt(now()->subDays(3))) {
            $score += 8;
        }
        $score += min(15, (int) $u->profile_completion / 100 * 15);
        if ($u->is_verified) {
            $score += 5;
        }
        if ($this->hasApprovedPhoto($u)) {
            $score += 5;
        }

        return round(min(100, $score), 2);
    }

    /**
     * Approved-photo check without N+1: prefers the eager
     * `approved_photos_count` (see scorePair/discover), falls back to avatar
     * or a single exists() query. photo_only semantics are approved-only.
     */
    public function hasApprovedPhoto(User $u): bool
    {
        $count = $u->getAttribute('approved_photos_count');
        if ($count !== null) {
            return (int) $count > 0 || ! empty($u->avatar_path);
        }

        return ! empty($u->avatar_path) || $u->photos()->where('status', 'approved')->exists();
    }

    protected function mutualGate(User $a, User $b): float
    {
        // If A explicitly requires a gender and B doesn't match, gate hard.
        foreach ([[$a, $b], [$b, $a]] as [$seeker, $cand]) {
            $gp = $seeker->partnerPreference?->gender_preference;
            $gp = $gp?->value ?? ($gp ? (string) $gp : null);
            $cg = $cand->gender?->value ?? ($cand->gender ? (string) $cand->gender : null);
            if ($gp && $cg && $gp !== $cg) {
                return 0.35;
            }
        }

        return 1.0;
    }

    /** Ranked candidates for a user with optional filters. */
    public function candidatesFor(User $u, array $filters = [], int $limit = 20): Collection
    {
        $u->loadMissing(['partnerPreference']);
        // Canonical pool (same hard filters as interactive discovery, plus
        // preference defaults for gender/age; likes are never excluded here).
        $query = app(CandidateRetrievalService::class)->pool($u, $filters, null, ['preferenceDefaults' => true]);

        $pool = $query->with(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers'])
            ->withCount(['photos as approved_photos_count' => fn ($q) => $q->where('status', 'approved')])
            ->limit(max($limit * 5, $limit + 20))->get();

        $blockedIds = $this->blockedIdsFor($u);
        $scored = $pool->map(function (User $cand) use ($u, $blockedIds) {
            if (! $this->passesHardFilterFast($u, $cand, $blockedIds)) {
                return null;
            }
            $r = $this->scorePair($u, $cand);
            $cand->compatibility = $r['mutual'];
            $cand->match_breakdown = $r['breakdown'];

            return $cand;
        })->filter()->sortByDesc('compatibility')->take($limit)->values();

        return $scored;
    }

    public function explain(User $a, User $b): array
    {
        $r = $this->scorePair($a, $b);
        $a->loadMissing(['profile', 'interests']);
        $b->loadMissing(['profile', 'interests']);

        $commonInterests = $a->interests->pluck('name')->intersect($b->interests->pluck('name'))->values()->all();
        $common = [];
        $diffs = [];
        if ($commonInterests) {
            $common[] = 'Shared interests: '.implode(', ', $commonInterests);
        }
        $ga = $a->profile?->relationship_goal;
        $gb = $b->profile?->relationship_goal;
        $gaV = $ga?->value ?? (string) $ga;
        $gbV = $gb?->value ?? (string) $gb;
        if ($gaV && $gbV) {
            if ($gaV === $gbV) {
                $common[] = "Same relationship goal: {$gaV}";
            } else {
                $diffs[] = "Goals differ: {$gaV} vs {$gbV}";
            }
        }
        if ($a->city && $b->city) {
            if (strtolower($a->city) === strtolower($b->city)) {
                $common[] = "Both in {$a->city}";
            } else {
                $diffs[] = "Different cities: {$a->city} vs {$b->city}";
            }
        }
        $aAge = $a->age();
        $bAge = $b->age();
        if ($aAge && $bAge) {
            $diff = abs($aAge - $bAge);
            if ($diff === 0) {
                $common[] = "Same age ({$aAge})";
            } else {
                $diffs[] = "Age difference: {$diff} tahun";
            }
        }
        $aHeight = $a->profile?->height_cm;
        $bHeight = $b->profile?->height_cm;
        if ($aHeight && $bHeight) {
            $diff = abs($aHeight - $bHeight);
            $diffs[] = "Height difference: {$diff} cm";
        }
        if ($a->profile?->occupation && $b->profile?->occupation) {
            if (strtolower($a->profile->occupation) === strtolower($b->profile->occupation)) {
                $common[] = "Same occupation: {$a->profile->occupation}";
            } else {
                $diffs[] = "Different work: {$a->profile->occupation} vs {$b->profile->occupation}";
            }
        }
        if ($a->profile?->education && $b->profile?->education) {
            if (strtolower($a->profile->education) === strtolower($b->profile->education)) {
                $common[] = "Same education: {$a->profile->education}";
            } else {
                $diffs[] = "Different education: {$a->profile->education} vs {$b->profile->education}";
            }
        }
        foreach ($r['breakdown'] as $k => $v) {
            if ($v < 40) {
                $diffs[] = "Low {$k} compatibility ({$v})";
            }
        }
        if (empty($diffs)) {
            $common[] = 'Hampir sempurna! Sangat cocok.';
        }

        return [
            'scores' => $r,
            'common' => $common,
            'differences' => $diffs,
            'breakdown' => $r['breakdown'],
            'summary' => [
                'mutual_score' => $r['mutual'],
                'strength' => $r['mutual'] >= 80 ? 'Sangat Cocok' : ($r['mutual'] >= 60 ? 'Cocok' : ($r['mutual'] >= 40 ? 'Cukup Cocok' : 'Kurang Cocok')),
                'recommendation' => $r['mutual'] >= 60 ? 'Layak untuk diajak ngobrol.' : 'Perlu lebih kenal sebelum ngobrol.',
            ],
        ];
    }

    public function scoreWithCache(User $a, User $b, int $ttl = 300): array
    {
        $ttl = max(60, min(3600, $ttl));
        $cacheKey = 'match:score:v'.$this->weightsVersion().':'.min($a->id, $b->id).':'.max($a->id, $b->id);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }
        $result = $this->scorePair($a, $b);
        Cache::put($cacheKey, $result, now()->addSeconds($ttl));

        return $result;
    }

    public function batchScore(User $user, array $candidateIds, int $limit = 50): array
    {
        $candidates = User::whereIn('id', array_slice($candidateIds, 0, $limit))->get();
        $blockedIds = $this->blockedIdsFor($user);
        $results = [];
        foreach ($candidates as $cand) {
            if (! $this->passesHardFilterFast($user, $cand, $blockedIds)) {
                continue;
            }
            $score = $this->scorePair($user, $cand);
            $results[$cand->id] = $score;
        }
        arsort($results);

        return $results;
    }

    public function persistScore(User $a, User $b): MatchScore
    {
        $r = $this->scorePair($a, $b);

        return DB::transaction(function () use ($a, $b, $r) {
            // Canonical single-row guarantee: exactly one row per pair (min,max).
            [$c1, $c2] = self::canonical((int) $a->id, (int) $b->id);

            return MatchScore::updateOrCreate(
                ['user_id' => $c1, 'candidate_id' => $c2],
                [
                    'questionnaire_score' => $r['breakdown']['personality'] ?? 0,
                    'interest_score' => $r['breakdown']['interest'] ?? 0,
                    'preference_score' => $r['breakdown']['preference'] ?? 0,
                    'activity_score' => $r['breakdown']['behavior'] ?? 0,
                    'total_score' => $r['mutual'],
                    'breakdown' => $r,
                    'computed_at' => now(),
                ]
            );
        });
    }

    /**
     * Batch scoring with the MatchScore read-path: fresh rows
     * (computed_at within recompute TTL) are reused, the rest is computed
     * live and persisted back via a single upsert. Returns id => result.
     */
    public function scoreMany(User $user, Collection $candidates, bool $persist = true): array
    {
        if ($candidates->isEmpty()) {
            return [];
        }
        $ttlHours = max(1, (int) config('matchmaking.recompute.ttl', 24));
        $freshSince = now()->subHours($ttlHours);
        $pairs = [];
        foreach ($candidates as $cand) {
            [$c1, $c2] = self::canonical((int) $user->id, (int) $cand->id);
            $pairs[$cand->id] = [$c1, $c2];
        }
        $rows = MatchScore::where(function ($q) use ($pairs) {
            foreach ($pairs as [$c1, $c2]) {
                $q->orWhere(fn ($qq) => $qq->where('user_id', $c1)->where('candidate_id', $c2));
            }
        })->where('computed_at', '>=', $freshSince)->get()
            ->keyBy(fn ($r) => $r->user_id.':'.$r->candidate_id);

        $results = [];
        $toPersist = [];
        foreach ($candidates as $cand) {
            [$c1, $c2] = $pairs[$cand->id];
            $row = $rows->get($c1.':'.$c2);
            $cached = is_array($row?->breakdown) ? $row->breakdown : null;
            if ($cached && isset($cached['mutual'], $cached['breakdown'])) {
                $results[$cand->id] = $cached;

                continue;
            }
            $r = $this->scorePair($user, $cand);
            $results[$cand->id] = $r;
            $toPersist[] = [
                'user_id' => $c1,
                'candidate_id' => $c2,
                'questionnaire_score' => $r['breakdown']['personality'] ?? 0,
                'interest_score' => $r['breakdown']['interest'] ?? 0,
                'preference_score' => $r['breakdown']['preference'] ?? 0,
                'activity_score' => $r['breakdown']['behavior'] ?? 0,
                'total_score' => $r['mutual'],
                'breakdown' => json_encode($r),
                'computed_at' => now()->toDateTimeString(),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }
        if ($persist && $toPersist) {
            try {
                MatchScore::upsert($toPersist, ['user_id', 'candidate_id'], [
                    'questionnaire_score', 'interest_score', 'preference_score',
                    'activity_score', 'total_score', 'breakdown', 'computed_at', 'updated_at',
                ]);
            } catch (\Throwable) {
            }
        }

        return $results;
    }

    public function demographicBreakdown(User $user): array
    {
        // Global aggregates change slowly; cache briefly to protect admin.
        return Cache::remember('matching:demographic', 600, function () use ($user) {
            return $this->demographicBreakdownFresh($user);
        });
    }

    protected function demographicBreakdownFresh(User $user): array
    {
        $base = fn () => User::active()->where('id', '!=', $user->id);

        $genderDist = $base()->selectRaw('gender, count(*) as total')->groupBy('gender')->pluck('total', 'gender');
        $ageBuckets = [
            '17-20' => $base()->whereBetween('date_of_birth', [now()->subYears(20)->toDateString(), now()->subYears(17)->toDateString()])->count(),
            '21-25' => $base()->whereBetween('date_of_birth', [now()->subYears(25)->toDateString(), now()->subYears(21)->toDateString()])->count(),
            '26-30' => $base()->whereBetween('date_of_birth', [now()->subYears(30)->toDateString(), now()->subYears(26)->toDateString()])->count(),
            '31-35' => $base()->whereBetween('date_of_birth', [now()->subYears(35)->toDateString(), now()->subYears(31)->toDateString()])->count(),
            '36-45' => $base()->whereBetween('date_of_birth', [now()->subYears(45)->toDateString(), now()->subYears(36)->toDateString()])->count(),
            '46+' => $base()->where('date_of_birth', '<=', now()->subYears(46)->toDateString())->count(),
        ];
        $verified = $base()->where('is_verified', true)->count();
        $premium = $base()->where('is_premium', true)->count();
        $online = $base()->where('is_online', true)->count();
        $total = $base()->count();

        $cityDist = $base()->whereNotNull('city')->selectRaw('city, count(*) as total')->groupBy('city')->orderByDesc('total')->limit(20)->pluck('total', 'city');

        return [
            'total' => $total,
            'gender' => $genderDist,
            'age_buckets' => $ageBuckets,
            'verified_count' => $verified,
            'premium_count' => $premium,
            'online_count' => $online,
            'verified_pct' => $total > 0 ? round($verified / $total * 100, 1) : 0,
            'premium_pct' => $total > 0 ? round($premium / $total * 100, 1) : 0,
            'online_pct' => $total > 0 ? round($online / $total * 100, 1) : 0,
            'top_cities' => $cityDist,
        ];
    }

    public function updateWeights(array $weights): array
    {
        $defaults = [
            'age' => 10, 'location' => 10, 'preference' => 20, 'personality' => 20,
            'interest' => 10, 'lifestyle' => 10, 'goal' => 10, 'behavior' => 10,
        ];
        $valid = array_intersect_key($weights, $defaults);
        if (empty($valid)) {
            return $this->weights();
        }
        config(['matchmaking.weights' => $valid]);

        return $this->weights();
    }
}
