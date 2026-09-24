<?php

namespace App\Jobs;

use App\Services\CallService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireRingingCalls implements ShouldQueue
{
    use Queueable, Concerns\HasScaleLimits;

    public $tries = 3;

    public $timeout = 120;

    public function handle(CallService $calls): void
    {
        $calls->expireRinging(100);
    }
}
