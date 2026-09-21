<?php

namespace App\Http\Resources;

use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $other = $viewer ? $this->otherMember((int) $viewer->id)?->user : null;
        $unread = null;
        if ($viewer) {
            try {
                $unread = app(ChatService::class)->unreadCount($this->resource, $viewer);
            } catch (\Throwable) {
                $unread = null;
            }
        }

        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'type' => $this->type?->value ?? $this->type,
            'title' => $this->title,
            'other_user' => $other ? UserResource::make($other) : null,
            'last_message' => MessageResource::make($this->whenLoaded('latestMessages') ? $this->latestMessages->first() : $this->messages()->latest('id')->first()),
            'unread_count' => $unread,
            'last_message_at' => $this->last_message_at,
            'is_pinned' => (bool) $this->is_pinned,
            'is_archived' => (bool) $this->is_archived,
            'updated_at' => $this->updated_at,
        ];
    }
}
