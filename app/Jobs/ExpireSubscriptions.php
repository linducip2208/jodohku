<?php

namespace App\Jobs;

use App\Services\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireSubscriptions implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 300;

    public function handle(SubscriptionService $subs): void
    {
        $subs->expireDue(500);
    }
}
