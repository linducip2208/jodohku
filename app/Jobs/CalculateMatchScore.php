<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MatchingEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculateMatchScore implements ShouldQueue
{
    use Queueable, Concerns\HasScaleLimits;

    public $tries = 3;

    public $timeout = 120;

    public function __construct(public int $userId, public int $candidateId) {}

    public function handle(MatchingEngine $engine): void
    {
        $a = User::find($this->userId);
        $b = User::find($this->candidateId);
        if (! $a || ! $b) {
            return;
        }
        $engine->persistScore($a, $b);
    }
}
