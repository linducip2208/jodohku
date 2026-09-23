<?php

namespace App\Livewire;

use App\Enums\CallStatus;
use App\Enums\CourtshipStage;
use App\Enums\CourtshipStatus;
use App\Enums\ScheduledMessageStatus;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\Courtship;
use App\Models\FraudRiskScore;
use App\Models\Message;
use App\Models\MessageBookmark;
use App\Models\ScheduledMessage;
use App\Services\AiService;
use App\Services\CallService;
use App\Services\ChatService;
use App\Services\GiftService;
use App\Services\MembershipService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;

class ChatWindow extends Component
{
    public int $conversationId;

    public string $body = '';

    public ?int $replyToId = null;

    public string $giftCode = '';

    public string $scheduleBody = '';

    public string $scheduleAt = '';

    public string $sticker = '';

    public string $pollQuestion = '';

    public string $pollOptions = '';

    public ?string $disappearing = null;

    public array $translations = [];

    public array $bookmarks = [];

    public array $pollResults = [];

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

    public function toggleBookmark(int $messageId, ChatService $chat): void
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
            if (MessageBookmark::where('message_id', $m->id)->where('user_id', $me->id)->exists()) {
                $chat->unbookmark($m, $me);
            } else {
                $chat->bookmark($m, $me);
            }
            $this->bookmarks = MessageBookmark::where('user_id', $me->id)
                ->whereIn('message_id', $this->loadedMessageIds())->pluck('message_id')->all();
        } catch (\Throwable) {
        }
    }

    protected function loadedMessageIds(): array
    {
        try {
            return Message::where('conversation_id', $this->conversationId)->latest('id')->take($this->perPage)->pluck('id')->all();
        } catch (\Throwable) {
            return [];
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

    protected function conv(): ?Conversation
    {
        return Conversation::find($this->conversationId);
    }

    public function schedule(ChatService $chat): void
    {
        $me = auth()->user();
        $conv = $this->conv();
        if (! $me || ! $conv || trim($this->scheduleBody) === '' || trim($this->scheduleAt) === '') {
            return;
        }
        try {
            $at = Carbon::parse($this->scheduleAt);
            $chat->scheduleMessage($conv, $me, trim($this->scheduleBody), $at, (string) Str::uuid());
            $this->scheduleBody = '';
            $this->scheduleAt = '';
            $this->dispatch('refresh-messages');
        } catch (\Throwable $e) {
            $this->addError('scheduleBody', $e->getMessage());
        }
    }

    public function cancelScheduled(int $id, ChatService $chat): void
    {
        $me = auth()->user();
        $item = ScheduledMessage::find($id);
        if (! $me || ! $item) {
            return;
        }
        try {
            $chat->cancelScheduled($item, $me);
            $this->dispatch('refresh-messages');
        } catch (\Throwable $e) {
            $this->addError('scheduleBody', $e->getMessage());
        }
    }

    public function sendSticker(ChatService $chat): void
    {
        $me = auth()->user();
        $conv = $this->conv();
        if (! $me || ! $conv || trim($this->sticker) === '') {
            return;
        }
        try {
            $chat->sendMessage($conv, $me, ['body' => trim($this->sticker), 'type' => 'sticker'], (string) Str::uuid());
            $this->sticker = '';
            $this->dispatch('refresh-messages');
        } catch (\Throwable $e) {
            $this->addError('sticker', $e->getMessage());
        }
    }

    public function sendPoll(ChatService $chat): void
    {
        $me = auth()->user();
        $conv = $this->conv();
        $options = array_values(array_filter(array_map(fn ($o) => trim(mb_substr((string) $o, 0, 120)), explode("\n", (string) $this->pollOptions))));
        if (! $me || ! $conv || trim($this->pollQuestion) === '' || count($options) < 2) {
            $this->addError('pollQuestion', 'Tulis pertanyaan dan minimal 2 opsi (satu per baris).');

            return;
        }
        try {
            $chat->sendMessage($conv, $me, [
                'body' => trim(mb_substr($this->pollQuestion, 0, 300)),
                'type' => 'poll',
                'metadata' => ['options' => array_slice($options, 0, 4)],
            ], (string) Str::uuid());
            $this->pollQuestion = '';
            $this->pollOptions = '';
            $this->dispatch('refresh-messages');
        } catch (\Throwable $e) {
            $this->addError('pollQuestion', $e->getMessage());
        }
    }

    public function votePoll(int $messageId, int $option, ChatService $chat): void
    {
        $me = auth()->user();
        $m = Message::find($messageId);
        if (! $me || ! $m) {
            return;
        }
        try {
            $this->pollResults[$messageId] = $chat->votePoll($m, $me, $option);
        } catch (\Throwable) {
        }
    }

    public function inviteCall(string $type, CallService $calls): void
    {
        $me = auth()->user();
        $conv = $this->conv();
        if (! $me || ! $conv || ! in_array($type, ['voice', 'video'], true)) {
            return;
        }
        try {
            $calls->invite($me, $conv, $type);
            $this->dispatch('refresh-messages');
        } catch (\Throwable $e) {
            $this->addError('body', $e->getMessage());
        }
    }

    public function answerCall(int $callId, string $action, CallService $calls): void
    {
        $me = auth()->user();
        $call = Call::find($callId);
        if (! $me || ! $call) {
            return;
        }
        try {
            match ($action) {
                'accept' => $calls->accept($call, $me),
                'reject' => $calls->reject($call, $me),
                'cancel' => $calls->cancel($call, $me),
                'end' => $calls->end($call, $me),
                default => null,
            };
            $this->dispatch('refresh-messages');
        } catch (\Throwable $e) {
            $this->addError('body', $e->getMessage());
        }
    }

    public function setDisappearing(): void
    {
        $me = auth()->user();
        $conv = $this->conv();
        if (! $me || ! $conv) {
            return;
        }
        if (! $conv->involves((int) $me->id)) {
            return;
        }
        $seconds = $this->disappearing === '' || $this->disappearing === null ? null : (int) $this->disappearing;
        if ($seconds !== null && ($seconds < 3600 || $seconds > 2592000)) {
            $this->addError('disappearing', 'Pilih durasi yang valid.');

            return;
        }
        $conv->update(['disappears_in_seconds' => $seconds]);
    }

    public function translate(int $messageId, AiService $ai): void
    {
        $me = auth()->user();
        $m = Message::with('conversation')->find($messageId);
        if (! $me || ! $m || ! $m->conversation || ! $m->conversation->involves((int) $me->id)) {
            return;
        }
        try {
            $res = $ai->chat('Terjemahkan teks berikut ke Bahasa Indonesia, jawab hanya hasil terjemahannya:\n"'.mb_substr((string) $m->body, 0, 1000).'"', ['max_tokens' => 400], $me, 'translate');
            $text = trim((string) $res['text']);
            $this->translations[$messageId] = $text !== '' ? $text : (string) $m->body;
        } catch (\Throwable) {
            $this->translations[$messageId] = (string) $m->body;
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
        $courtshipStage = null;
        $courtshipProgress = null;
        $peerRisk = null;
        $scheduled = collect();
        $activeCall = null;
        if ($conv && $me) {
            try {
                $chat->markRead($conv, $me);
                $messages = Message::where('conversation_id', $conv->id)->with(['sender', 'reactions', 'replyTo', 'attachments', 'reads'])->latest('id')->take($this->perPage)->get()->reverse()->values();
                $other = $conv->otherUser($me->id);
                $canSeeReads = (bool) ($membership->currentFeatures($me)['has_read_receipts'] ?? false);
                $giftCatalog = $gifts->catalog();
                $scheduled = ScheduledMessage::where('conversation_id', $conv->id)
                    ->where('sender_id', $me->id)->where('status', ScheduledMessageStatus::Pending)
                    ->orderBy('send_at')->get();
                $activeCall = Call::where('conversation_id', $conv->id)
                    ->whereIn('status', [CallStatus::Ringing, CallStatus::Ongoing])
                    ->latest('id')->first();
                $this->bookmarks = MessageBookmark::where('user_id', $me->id)
                    ->whereIn('message_id', $messages->pluck('id'))->pluck('message_id')->all();
                if ($other) {
                    $courtship = Courtship::where(fn ($q) => $q
                        ->where(fn ($qq) => $qq->where('initiator_id', $me->id)->where('partner_id', $other->id))
                        ->orWhere(fn ($qq) => $qq->where('initiator_id', $other->id)->where('partner_id', $me->id)))
                        ->where('status', CourtshipStatus::Active)->latest('id')->first();
                    $courtshipStage = $courtship?->stage->label();
                    if ($courtship) {
                        $stages = CourtshipStage::cases();
                        $idx = array_search($courtship->stage, $stages, true);
                        $courtshipProgress = [
                            'label' => $courtship->stage->label(),
                            'index' => $idx === false ? 0 : $idx + 1,
                            'total' => count($stages),
                            'courtship_id' => $courtship->id,
                        ];
                    }
                    $peerRisk = FraudRiskScore::where('user_id', $other->id)->latest('id')->value('level');
                }
            } catch (\Throwable) {
            }
        }

        return view('livewire.chat-window', ['conv' => $conv, 'messages' => $messages, 'other' => $other, 'me' => $me, 'canSeeReads' => $canSeeReads, 'giftCatalog' => $giftCatalog, 'courtshipStage' => $courtshipStage, 'courtshipProgress' => $courtshipProgress ?? null, 'peerRisk' => $peerRisk, 'scheduledItems' => $scheduled, 'activeCall' => $activeCall, 'stickerCatalog' => $chat->stickers(), 'bookmarks' => $this->bookmarks]);
    }
}
