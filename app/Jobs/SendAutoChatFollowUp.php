<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\AiChatAssistantService;
use App\Services\ChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAutoChatFollowUp implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $conversationId) {}

    public function handle(ChatService $chat, AiChatAssistantService $assistant): void
    {
        $conv = Conversation::with(['members.user', 'messages'])->find($this->conversationId);
        if (! $conv) {
            return;
        }
        $last = $conv->messages()->latest('id')->first();
        if (! $last || $last->created_at->gt(now()->subHours(24))) {
            return; // only nudge stale conversations
        }
        // Pick the virtual/ai sender if present, else first member
        $sender = $conv->members->first()?->user;
        if (! $sender || ! $sender->account_type?->isSynthetic()) {
            return;
        }
        try {
            $ice = $assistant->icebreakers($sender, $conv->members->last()?->user ?? $sender, 1);
            $chat->sendMessage($conv, $sender, ['body' => $ice[0] ?? 'Hai! Masih di sana? 😊', 'metadata' => ['follow_up' => true]]);
        } catch (\Throwable) {
            return;
        }
    }
}
