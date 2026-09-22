<?php

namespace App\Jobs;

use App\Services\CallService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireRingingCalls implements ShouldQueue
{
    use Queueable;

    public function handle(CallService $calls): void
    {
        $calls->expireRinging(100);
    }
}
