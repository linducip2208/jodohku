<?php

namespace App\Jobs;

use App\Services\ChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchScheduledMessages implements ShouldQueue
{
    use Queueable, Concerns\HasScaleLimits;

    public $tries = 3;

    public $timeout = 300;

    public function handle(ChatService $chat): void
    {
        $chat->dispatchDue(100);
    }
}
