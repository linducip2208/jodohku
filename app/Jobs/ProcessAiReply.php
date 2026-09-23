<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\VirtualConversation;
use App\Services\AiService;
use App\Services\ChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAiReply implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $conversationId, public int $virtualProfileId) {}

    public function handle(AiService $ai, ChatService $chat): void
    {
        $conv = Conversation::find($this->conversationId);
        $vc = VirtualConversation::where('conversation_id', $this->conversationId)
            ->where('virtual_profile_id', $this->virtualProfileId)->first();
        if (! $conv || ! $vc) {
            return;
        }
        if (! in_array($vc->status->value ?? (string) $vc->status, ['active'], true)) {
            return;
        }
        $virtualUser = $vc->virtualProfile?->user;
        if (! $virtualUser) {
            return;
        }
        $lastReal = $conv->messages()->where('sender_id', '!=', $virtualUser->id)->latest('id')->first();
        if (! $lastReal) {
            return;
        }
        $prompt = 'Kamu persona ramah '.config('app.name').'. Balas pesan berikut singkat (max 50 kata), Bahasa Indonesia, sopan: "'.$lastReal->body.'"';
        try {
            $res = $ai->chat($prompt, ['max_tokens' => 150], null, 'virtual_ai_reply');
            $chat->sendMessage($conv, $virtualUser, [
                'body' => trim((string) $res['text']),
                'metadata' => ['ai_generated' => true],
            ]);
        } catch (\Throwable) {
            return;
        }
    }
}
