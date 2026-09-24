<?php

namespace App\Jobs;

use App\Services\BoostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireBoost implements ShouldQueue
{
    use Queueable, Concerns\HasScaleLimits;

    public $tries = 3;

    public $timeout = 300;

    public function handle(BoostService $boosts): void
    {
        $boosts->expireDue(500);
    }
}
