<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Forum;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Lightweight personalization composer (NOT a matching engine).
 *
 * Composes ONLY existing services/queries: MatchingEngine + DiscoveryService
 * for members, plain Eloquent for community, AiChatAssistantService for
 * topics. Every result links to a real profile/record; empty inputs yield
 * empty outputs (never fake recommendations). Blocked/inactive content is
 * filtered by the underlying services.
 */
class PersonalizationService
{
    public function __construct(
        protected MatchingEngine $engine,
        protected DiscoveryService $discovery,
        protected AiChatAssistantService $assistant,
    ) {}

    /** @return array{picks:array, ids:array} */
    public function getRecommendedMembers(User $user, int $limit = 6): array
    {
        try {
            $picks = $this->discovery->dailyPicks($user, $limit);
        } catch (\Throwable) {
            return ['picks' => [], 'ids' => []];
        }
        $ids = array_column($picks, 'user_id');
        $users = $ids ? User::whereIn('id', $ids)->with(['profile', 'interests'])->get()->keyBy('id') : collect();

        return ['picks' => $picks, 'users' => $users];
    }

    public function getNewMembers(int $limit = 6): Collection
    {
        try {
            return User::active()->real()->latest('id')->limit($limit)->with(['profile'])->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function getActiveNow(User $user, int $limit = 6): Collection
    {
        try {
            return User::active()->real()->where('is_online', true)->where('id', '!=', $user->id)
                ->limit($limit)->with(['profile'])->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function getRecommendedEvents(User $user, int $limit = 3): Collection
    {
        try {
            return Event::open()->where('starts_at', '>=', now())
                ->when($user->city, fn ($q) => $q->orderByRaw('CASE WHEN city = ? THEN 0 ELSE 1 END', [$user->city]))
                ->orderBy('starts_at')->limit($limit)->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function getRecommendedForums(User $user, int $limit = 3): Collection
    {
        try {
            $user->loadMissing(['interests']);
            $mine = $user->interests->pluck('name')->map(fn ($n) => mb_strtolower((string) $n))->all();

            return Forum::where('is_active', true)->withCount(['visibleThreads'])->orderBy('sort_order')->limit(20)->get()
                ->map(function (Forum $f) use ($mine) {
                    $score = 0;
                    foreach ($mine as $interest) {
                        if ($interest !== '' && str_contains(mb_strtolower($f->name.' '.$f->description), $interest)) {
                            $score++;
                        }
                    }
                    $f->setAttribute('recommend_score', $score);

                    return $f;
                })->sortByDesc('recommend_score')->take($limit)->values();
        } catch (\Throwable) {
            return collect();
        }
    }

    /** Taaruf topic suggestions grounded on already-visible data. */
    public function getRecommendedTopics(User $user, User $candidate, int $count = 3): array
    {
        try {
            return array_slice($this->assistant->taarufTopics($user, $candidate, $count), 0, $count);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Match explanation for a pair (shared presenter, privacy-safe). */
    public function explainFor(User $a, User $b): ?array
    {
        try {
            return app(MatchExplanation::class)->for($a, $b);
        } catch (\Throwable) {
            return null;
        }
    }
}
