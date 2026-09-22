<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    public function broadcastWith(): array
    {
        $status = $this->message->status instanceof \BackedEnum ? $this->message->status->value : $this->message->status;
        $isHidden = in_array($status, ['deleted', 'moderated'], true);

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'body' => $isHidden ? null : $this->message->body,
            'status' => $status,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
