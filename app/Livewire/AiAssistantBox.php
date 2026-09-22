<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Services\AiChatAssistantService;
use Livewire\Component;

class AiAssistantBox extends Component
{
    public int $conversationId;

    public array $suggestions = [];

    public function mount(int $conversationId): void
    {
        $this->conversationId = $conversationId;
    }

    public function load(AiChatAssistantService $ai): void
    {
        $me = auth()->user();
        $conv = Conversation::find($this->conversationId);
        if (! $me || ! $conv) {
            $this->suggestions = [];

            return;
        }
        try {
            $this->suggestions = $ai->suggestedReplies($conv, $me, 3);
        } catch (\Throwable) {
            $this->suggestions = ['Hai! Ceritakan weekend serumu dong 😊', 'Aku lihat kamu suka traveling — destinasi favoritmu?', 'Ngopi bareng weekend ini? ☕'];
        }
    }

    public function pick(string $text): void
    {
        $this->dispatch('ai-pick-reply', text: $text);
    }

    public function render()
    {
        return view('livewire.ai-assistant-box');
    }
}
