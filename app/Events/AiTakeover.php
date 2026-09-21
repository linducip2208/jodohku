<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiTakeover implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Conversation $conversation, public User $operator, public string $mode = 'operator') {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->conversation->id)];
    }

    public function broadcastAs(): string { return 'ai.takeover'; }

    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversation->id, 'operator_id' => $this->operator->id, 'mode' => $this->mode];
    }
}
