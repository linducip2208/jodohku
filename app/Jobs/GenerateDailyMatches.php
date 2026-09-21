<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MatchingEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDailyMatches implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $batch = 100) {}

    public function handle(MatchingEngine $engine): void
    {
        $minScore = (float) config('matchmaking.thresholds.min_score_for_daily_pick', 55);
        User::active()->real()->limit($this->batch)->get()->each(function (User $user) use ($engine, $minScore) {
            $cands = $engine->candidatesFor($user, [], (int) config('matchmaking.thresholds.daily_picks', 10));
            foreach ($cands as $cand) {
                if (($cand->compatibility ?? 0) >= $minScore) {
                    $engine->persistScore($user, $cand);
                }
            }
        });
    }
}
