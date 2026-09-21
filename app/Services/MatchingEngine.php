<?php

namespace App\Services;

use App\Models\Block;
use App\Models\MatchScore;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MatchingEngine
{
    public function weights(): array
    {
        $defaults = [
            'age' => 10, 'location' => 10, 'preference' => 20, 'personality' => 20,
            'interest' => 10, 'lifestyle' => 10, 'goal' => 10, 'behavior' => 10,
        ];

        try {
            $configured = config('matchmaking.weights', $defaults);
        } catch (\Throwable) {
            $configured = $defaults;
        }

        $merged = array_merge($defaults, is_array($configured) ? $configured : []);
        $total = max(1, array_sum($merged));

        // Normalize to 100
        foreach ($merged as $k => $v) {
            $merged[$k] = round($v / $total * 100, 2);
        }

        return $merged;
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

    public function scorePair(User $a, User $b): array
    {
        $a->loadMissing(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers']);
        $b->loadMissing(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers']);

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
            $hasPhoto = $candidate->photos()->exists();
            $scores[] = $hasPhoto ? 100 : 20;
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
        if ($u->photos()->exists()) {
            $score += 5;
        }

        return round(min(100, $score), 2);
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
        $query = User::query()->active()->where('id', '!=', $u->id);

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        } elseif ($u->partnerPreference?->gender_preference) {
            $gp = $u->partnerPreference->gender_preference;
            $query->where('gender', $gp instanceof \BackedEnum ? $gp->value : (string) $gp);
        }
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
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
        // Age filter
        $minAge = $filters['min_age'] ?? $u->partnerPreference?->min_age;
        $maxAge = $filters['max_age'] ?? $u->partnerPreference?->max_age;
        if ($minAge) {
            $query->where('date_of_birth', '<=', now()->subYears((int) $minAge)->toDateString());
        }
        if ($maxAge) {
            $query->where('date_of_birth', '>=', now()->subYears((int) $maxAge + 1)->toDateString());
        }
        // Exclude blocked
        $blockedIds = Block::where('blocker_id', $u->id)->pluck('blocked_id')
            ->merge(Block::where('blocked_id', $u->id)->pluck('blocker_id'))->all();
        if ($blockedIds) {
            $query->whereNotIn('id', $blockedIds);
        }
        if (! empty($filters['exclude_ids'])) {
            $query->whereNotIn('id', (array) $filters['exclude_ids']);
        }

        $pool = $query->with(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers'])
            ->limit(max($limit * 5, $limit + 20))->get();

        $scored = $pool->map(function (User $cand) use ($u) {
            if (! $this->passesHardFilter($u, $cand)) {
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
        foreach ($r['breakdown'] as $k => $v) {
            if ($v < 40) {
                $diffs[] = "Low {$k} compatibility ({$v})";
            }
        }

        return [
            'scores' => $r,
            'common' => $common,
            'differences' => $diffs,
        ];
    }

    /** Persist directional + canonical match_scores rows inside a transaction. */
    public function persistScore(User $a, User $b): MatchScore
    {
        $r = $this->scorePair($a, $b);

        return DB::transaction(function () use ($a, $b, $r) {
            [$c1, $c2] = self::canonical((int) $a->id, (int) $b->id);
            $isCanonicalOrder = ((int) $a->id === $c1);

            $row = MatchScore::updateOrCreate(
                ['user_id' => $a->id, 'candidate_id' => $b->id],
                [
                    'questionnaire_score' => $r['breakdown']['personality'] ?? 0,
                    'interest_score' => $r['breakdown']['interest'] ?? 0,
                    'preference_score' => $r['breakdown']['preference'] ?? 0,
                    'activity_score' => $r['breakdown']['behavior'] ?? 0,
                    'total_score' => $r['a_to_b'],
                    'breakdown' => $r,
                    'computed_at' => now(),
                ]
            );

            // Canonical single-row guarantee: also upsert the canonical orientation
            // so (min,max) always exists for fast pair lookups.
            if (! $isCanonicalOrder) {
                MatchScore::updateOrCreate(
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
            }

            return $row;
        });
    }
}
