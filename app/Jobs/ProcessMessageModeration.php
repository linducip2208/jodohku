<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\AiModerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMessageModeration implements ShouldQueue
{
    use Concerns\HasScaleLimits, Queueable;

    public $tries = 3;

    public $timeout = 120;

    public function __construct(public int $messageId) {}

    public function handle(AiModerationService $ai): void
    {
        $message = Message::find($this->messageId);
        if (! $message) {
            return;
        }
        // Only AI-review when local pipeline flagged it
        $flags = $message->metadata['moderation']['flags'] ?? [];
        $risk = (int) ($message->metadata['moderation']['risk'] ?? 0);
        if ($risk < 35 && empty($flags)) {
            return;
        }
        $ai->review($message);
    }
}
