<?php

namespace App\Events;

use App\Models\ChatRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatRequestCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public ChatRequest $chatRequest) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->chatRequest->receiver_id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.requested';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->chatRequest->id, 'sender_id' => $this->chatRequest->sender_id];
    }
}
