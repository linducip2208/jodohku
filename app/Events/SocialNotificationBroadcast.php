<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Realtime leg of social notifications (the database row is the source
 * of truth; this only nudges the bell). Privacy-safe payload: type +
 * title only, never bodies or private fields.
 */
class SocialNotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $userId, public string $type, public string $title) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    public function broadcastWith(): array
    {
        return ['type' => $this->type, 'title' => mb_substr($this->title, 0, 140)];
    }
}
