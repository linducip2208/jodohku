<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use App\Services\GiftService;
use App\Services\MembershipService;
use Illuminate\Support\Str;
use Livewire\Component;

class ChatWindow extends Component
{
    public int $conversationId;

    public string $body = '';

    public ?int $replyToId = null;

    public string $giftCode = '';

    public string $typingUsers = '';

    public int $perPage = 30;

    public function mount(int $conversationId): void
    {
        $this->conversationId = $conversationId;
    }

    protected function getListeners(): array
    {
        return [
            'echo-message' => 'onEchoMessage',
            'refresh-messages' => '$refresh',
        ];
    }

    public function echoChannel(): string
    {
        return 'conversations.'.$this->conversationId;
    }

    public function onEchoMessage($payload = null): void
    {
        $cid = is_array($payload) ? ($payload['conversationId'] ?? null) : null;
        if ($cid === null || (int) $cid === (int) $this->conversationId) {
            $this->dispatch('$refresh');
        }
    }

    public function send(ChatService $chat): void
    {
        $me = auth()->user();
        if (! $me || trim($this->body) === '') {
            return;
        }
        $conv = Conversation::find($this->conversationId);
        if (! $conv) {
            return;
        }
        try {
            $chat->sendMessage($conv, $me, [
                'body' => trim($this->body),
                'reply_to_id' => $this->replyToId,
            ], (string) Str::uuid());
            $this->body = '';
            $this->replyToId = null;
        } catch (\Throwable $e) {
            $this->addError('body', $e->getMessage());
        }
    }

    public function react(int $messageId, string $emoji, ChatService $chat): void
    {
        $me = auth()->user();
        if (! $me) {
            return;
        }
        $m = Message::find($messageId);
        if (! $m) {
            return;
        }
        try {
            $chat->react($m, $me, $emoji);
        } catch (\Throwable) {
        }
    }

    public function sendGift(GiftService $gifts): void
    {
        $me = auth()->user();
        $conv = Conversation::find($this->conversationId);
        if (! $me || ! $conv || trim($this->giftCode) === '') {
            return;
        }
        $other = $conv->otherUser($me->id);
        if (! $other) {
            return;
        }
        try {
            $gifts->send($me, $other, trim($this->giftCode), 1, $conv);
            $this->giftCode = '';
            $this->dispatch('refresh-messages');
        } catch (\Throwable $e) {
            $this->addError('giftCode', $e->getMessage());
        }
    }

    public function delete(int $messageId, ChatService $chat): void
    {
        $me = auth()->user();
        if (! $me) {
            return;
        }
        $m = Message::find($messageId);
        if (! $m) {
            return;
        }
        try {
            $chat->deleteMessage($m, $me, 'for_me');
        } catch (\Throwable) {
        }
    }

    public function markTyping(ChatService $chat, bool $isTyping = true): void
    {
        $me = auth()->user();
        $conv = Conversation::find($this->conversationId);
        if ($me && $conv) {
            try {
                $chat->typing($conv, $me, $isTyping);
            } catch (\Throwable) {
            }
        }
    }

    public function render(ChatService $chat, MembershipService $membership, GiftService $gifts)
    {
        $me = auth()->user();
        $conv = Conversation::with(['users', 'members.user'])->find($this->conversationId);
        $messages = collect();
        $other = null;
        $canSeeReads = false;
        $giftCatalog = collect();
        if ($conv && $me) {
            try {
                $chat->markRead($conv, $me);
                $messages = Message::where('conversation_id', $conv->id)->with(['sender', 'reactions', 'replyTo', 'attachments', 'reads'])->latest('id')->take($this->perPage)->get()->reverse()->values();
                $other = $conv->otherUser($me->id);
                $canSeeReads = (bool) ($membership->currentFeatures($me)['has_read_receipts'] ?? false);
                $giftCatalog = $gifts->catalog();
            } catch (\Throwable) {
            }
        }

        return view('livewire.chat-window', ['conv' => $conv, 'messages' => $messages, 'other' => $other, 'me' => $me, 'canSeeReads' => $canSeeReads, 'giftCatalog' => $giftCatalog]);
    }
}
