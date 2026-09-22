<?php

namespace App\Jobs;

use App\Services\ChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneDisappearingMessages implements ShouldQueue
{
    use Queueable;

    public function handle(ChatService $chat): void
    {
        $chat->pruneDisappearing(200);
    }
}
