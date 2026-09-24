<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MatchingEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDailyMatches implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    /** Heavy nightly batch: few long attempts, not many quick ones. */
    public $tries = 2;

    public $timeout = 1800;

    public function backoff(): array
    {
        return [600, 1800];
    }

    public function __construct(public int $batch = 100) {}

    public function handle(MatchingEngine $engine): void
    {
        $minScore = (float) config('matchmaking.thresholds.min_score_for_daily_pick', 55);
        $dailyPicks = (int) config('matchmaking.thresholds.daily_picks', 10);
        // chunkById: every active real member is visited exactly once —
        // the old limit(batch) always processed the same first N users.
        User::active()->real()->orderBy('id')->chunkById(max(50, $this->batch), function ($users) use ($engine, $minScore, $dailyPicks) {
            foreach ($users as $user) {
                try {
                    $cands = $engine->candidatesFor($user, [], $dailyPicks);
                } catch (\Throwable) {
                    continue;
                }
                foreach ($cands as $cand) {
                    if (($cand->compatibility ?? 0) >= $minScore) {
                        try {
                            $engine->persistScore($user, $cand);
                        } catch (\Throwable) {
                        }
                    }
                }
            }
        });
    }
}
