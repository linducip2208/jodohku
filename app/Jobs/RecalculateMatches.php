<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MatchingEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateMatches implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 2;

    public $timeout = 600;

    public function backoff(): array
    {
        return [120, 600];
    }

    public function __construct(public int $userId, public int $limit = 30) {}

    public function handle(MatchingEngine $engine): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }
        $candidates = $engine->candidatesFor($user, [], $this->limit);
        foreach ($candidates as $cand) {
            $engine->persistScore($user, $cand);
        }
    }
}
