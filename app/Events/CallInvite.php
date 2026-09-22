<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallInvite implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Call $call) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->call->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'call.invite';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->call->id,
            'conversation_id' => $this->call->conversation_id,
            'caller_id' => $this->call->caller_id,
            'receiver_id' => $this->call->receiver_id,
            'type' => $this->call->type,
            'status' => $this->call->status->value,
        ];
    }
}
