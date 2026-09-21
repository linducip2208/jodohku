<?php

namespace App\Jobs;

use App\Services\BoostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireBoost implements ShouldQueue
{
    use Queueable;

    public function handle(BoostService $boosts): void
    {
        $boosts->expireDue(500);
    }
}
