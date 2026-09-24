<?php

namespace App\Services;

use App\Models\Follow;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Social dating recommendations. Dating compatibility comes ONLY from
 * MatchingEngine (untouched); this layer adds a SEPARATE social_score
 * from graph signals and returns human-safe explanations — never
 * deterministic claims ("jodohmu", percentages-as-fate).
 */
class SocialDatingRecommendationService
{
    public function __construct(
        protected MatchingEngine $engine,
        protected CandidateRetrievalService $retrieval,
        protected AnalyticsService $analytics,
    ) {}

    /**
     * @return Collection<int, array{user:User, compatibility:float, social_score:int, reasons:string[]}>
     */
    public function recommend(User $user, array $filters = [], int $limit = 10): Collection
    {
        $candidates = $this->retrieval->pool($user, $filters, null, ['preferenceDefaults' => true])
            ->with(['profile', 'partnerPreference', 'interests'])
            ->limit(max($limit * 5, $limit + 20))->get();

        $blockedIds = $this->engine->blockedIdsFor($user);
        $myFollows = Follow::where('follower_id', $user->id)->pluck('followed_id')->all();
        $myGroups = GroupMember::where('user_id', $user->id)->pluck('group_id')->all();
        $interestIds = $user->interests()->pluck('interests.id')->all();

        $scored = $candidates->map(function (User $cand) use ($user, $blockedIds, $myFollows, $myGroups, $interestIds) {
            if (! $this->engine->passesHardFilterFast($user, $cand, $blockedIds)) {
                return null;
            }
            $r = $this->engine->scorePair($user, $cand);
            $social = 0;
            $reasons = [];
            $shared = $cand->interests->pluck('id')->intersect($interestIds)->count();
            if ($shared > 0) {
                $social += $shared * 5;
                $reasons[] = 'Memiliki beberapa minat yang sama';
            }
            if (Follow::where('follower_id', $cand->id)->whereIn('followed_id', $myFollows)->exists()) {
                $social += 8;
                $reasons[] = 'Terhubung dengan lingkaran sosialmu';
            }
            if ($myGroups && GroupMember::where('user_id', $cand->id)->whereIn('group_id', $myGroups)->exists()) {
                $social += 10;
                $reasons[] = 'Sering aktif di topik yang sama';
            }
            if (! empty($cand->partnerPreference?->relationship_goal) && ! empty($user->partnerPreference?->relationship_goal)
                && (string) $cand->partnerPreference->relationship_goal === (string) $user->partnerPreference->relationship_goal) {
                $social += 6;
                $reasons[] = 'Preferensi dating kalian memiliki beberapa kesamaan';
            }

            return [
                'user' => $cand,
                'compatibility' => $r['mutual'],
                'social_score' => $social,
                'reasons' => array_values(array_unique($reasons)),
            ];
        })->filter()->sortByDesc(fn ($r) => $r['compatibility'] * 0.7 + $r['social_score'] * 0.3)
            ->take($limit)->values();

        return $scored;
    }
}
