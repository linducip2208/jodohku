<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $isHidden = in_array($this->message->status?->value, ['deleted', 'moderated'], true);
        if (is_string($this->message->status) && in_array($this->message->status, ['deleted', 'moderated'], true)) {
            $isHidden = true;
        }

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'body' => $isHidden ? null : $this->message->body,
            'type' => $this->message->type,
            'client_message_id' => $this->message->client_message_id,
            'is_edited' => (bool) ($this->message->is_edited ?? false),
            'status' => $this->message->status instanceof \BackedEnum ? $this->message->status->value : $this->message->status,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
