<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender' => $this->whenLoaded('sender', fn () => ['id' => $this->sender->id, 'display_name' => $this->sender->displayName()]),
            'body' => $this->body,
            'type' => $this->type,
            'status' => $this->status?->value ?? $this->status,
            'client_message_id' => $this->client_message_id,
            'reply_to_id' => $this->reply_to_id,
            'is_edited' => (bool) $this->is_edited,
            'is_ai_generated' => (bool) $this->is_ai_generated,
            'attachments' => $this->whenLoaded('attachments'),
            'reactions' => $this->whenLoaded('reactions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
