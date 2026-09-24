<?php

namespace App\Jobs;

use App\Services\ChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneDisappearingMessages implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 600;

    public function handle(ChatService $chat): void
    {
        $chat->pruneDisappearing(200);
    }
}
