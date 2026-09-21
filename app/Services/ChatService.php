<?php

namespace App\Services;

use App\Enums\ConversationType;
use App\Events\MessageRead as MessageReadEvent;
use App\Events\MessageReceived;
use App\Events\MessageSent;
use App\Events\TypingIndicator;
use App\Models\Block;
use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\ConversationUserSetting;
use App\Models\Message;
use App\Models\MessageDeletion;
use App\Models\MessageReaction;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(protected MessageModerationService $moderation) {}

    protected function ensureCanChat(User $a, User $b): void
    {
        if ($a->id === $b->id) {
            throw new \InvalidArgumentException('Cannot chat with yourself.');
        }
        if (! $a->canChat() || ! $b->canChat()) {
            throw new \RuntimeException('Account not allowed to chat.');
        }
        if (Block::existsBetween((int) $a->id, (int) $b->id)) {
            throw new \RuntimeException('Chat blocked between these users.');
        }
        if (ChatBlock::where(fn ($q) => $q->where('blocker_id', $a->id)->where('blocked_id', $b->id))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b->id)->where('blocked_id', $a->id))->exists()) {
            throw new \RuntimeException('Conversation blocked.');
        }
    }

    public function findOrCreateDirect(User $a, User $b): Conversation
    {
        $this->ensureCanChat($a, $b);

        return DB::transaction(function () use ($a, $b) {
            $existing = Conversation::findDirect((int) $a->id, (int) $b->id);
            if ($existing) {
                return $existing;
            }
            [$u1, $u2] = UserMatch::canonical((int) $a->id, (int) $b->id);
            $match = UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->first();

            $conv = Conversation::create([
                'type' => ConversationType::Direct,
                'match_id' => $match?->id,
                'created_by' => $a->id,
            ]);
            ConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $a->id, 'joined_at' => now()]);
            ConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $b->id, 'joined_at' => now()]);

            return $conv->fresh(['members']);
        });
    }

    protected function checkRateLimit(User $sender): void
    {
        $isPremium = $sender->isPremium();
        $perMin = config($isPremium ? 'chat.rate_limits.premium.messages_per_minute' : 'chat.rate_limits.messages_per_minute', 20);
        $perHour = config($isPremium ? 'chat.rate_limits.premium.messages_per_hour' : 'chat.rate_limits.messages_per_hour', 200);
        $minCount = Message::where('sender_id', $sender->id)->where('created_at', '>', now()->subMinute())->count();
        if ($minCount >= $perMin) {
            throw new \RuntimeException('Rate limit: too many messages per minute.');
        }
        $hourCount = Message::where('sender_id', $sender->id)->where('created_at', '>', now()->subHour())->count();
        if ($hourCount >= $perHour) {
            throw new \RuntimeException('Rate limit: too many messages per hour.');
        }
    }

    /**
     * @param array{body?:string,type?:string,reply_to_id?:int,attachments?:array,metadata?:array} $data
     */
    public function sendMessage(Conversation $conversation, User $sender, array $data, ?string $clientMessageId = null): Message
    {
        if (! $conversation->involves((int) $sender->id)) {
            throw new \RuntimeException('Not a conversation member.');
        }
        if ($conversation->is_blocked) {
            throw new \RuntimeException('Conversation is blocked.');
        }
        $other = $conversation->otherMember((int) $sender->id);
        if ($other && $other->user) {
            $this->ensureCanChat($sender, $other->user);
        }
        $this->checkRateLimit($sender);

        $body = trim((string) ($data['body'] ?? ''));
        $maxLen = (int) config('chat.message.max_length', 2000);
        if ($body === '' && empty($data['attachments'])) {
            throw new \InvalidArgumentException('Message body or attachment required.');
        }
        if (mb_strlen($body) > $maxLen) {
            throw new \InvalidArgumentException('Message too long.');
        }

        // Idempotency on (conversation_id, client_message_id)
        $clientId = $clientMessageId ?? (string) ($data['client_message_id'] ?? (string) Str::uuid());
        $existing = Message::where('conversation_id', $conversation->id)->where('client_message_id', $clientId)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($conversation, $sender, $data, $body, $clientId) {
            $mod = $this->moderation->moderate($body, $sender, null);
            if ($mod['decision'] === 'block') {
                throw new \RuntimeException('Message blocked by moderation: '.implode(', ', array_slice($mod['flags'], 0, 3)));
            }
            $finalBody = $mod['clean'];

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $finalBody,
                'type' => $data['type'] ?? 'text',
                'status' => $mod['decision'] === 'flag' ? \App\Enums\MessageStatus::Sent : \App\Enums\MessageStatus::Sent,
                'client_message_id' => $clientId,
                'reply_to_id' => $data['reply_to_id'] ?? null,
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'moderation' => ['risk' => $mod['risk'], 'decision' => $mod['decision'], 'flags' => $mod['flags']],
                ]),
            ]);

            if (! empty($data['attachments']) && is_array($data['attachments'])) {
                foreach (array_slice($data['attachments'], 0, (int) config('chat.message.max_attachments', 5)) as $att) {
                    $message->attachments()->create([
                        'file_path' => $att['file_path'] ?? '',
                        'file_name' => $att['file_name'] ?? null,
                        'mime_type' => $att['mime_type'] ?? null,
                        'file_size' => $att['file_size'] ?? 0,
                        'width' => $att['width'] ?? null,
                        'height' => $att['height'] ?? null,
                        'duration_seconds' => $att['duration_seconds'] ?? null,
                    ]);
                }
            }

            $conversation->update(['last_message_at' => now()]);

            event(new MessageSent($message->fresh(['sender', 'conversation'])));
            event(new MessageReceived($message->fresh(['sender', 'conversation'])));
            \App\Jobs\SendChatNotification::dispatch($message->id);

            // Queue AI review only when flagged
            if ($mod['needs_ai']) {
                \App\Jobs\ProcessMessageModeration::dispatch($message->id);
            }

            return $message->fresh();
        });
    }

    public function editMessage(Message $message, User $user, string $newBody): Message
    {
        if ((int) $message->sender_id !== (int) $user->id) {
            throw new \RuntimeException('Only sender can edit.');
        }
        $mod = $this->moderation->moderate($newBody, $user, $message);
        if ($mod['decision'] === 'block') {
            throw new \RuntimeException('Edited message blocked by moderation.');
        }
        $message->update(['body' => $mod['clean'], 'is_edited' => true]);

        return $message->fresh();
    }

    /** Soft delete per user; for_everyone only by sender. */
    public function deleteMessage(Message $message, User $user, string $scope = 'for_me'): bool
    {
        if ($scope === 'for_everyone' && (int) $message->sender_id !== (int) $user->id) {
            throw new \RuntimeException('Only sender can delete for everyone.');
        }

        return (bool) MessageDeletion::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $user->id, 'scope' => $scope],
            []
        );
    }

    /**
     * Store an uploaded chat file and send it as a message in one call.
     * Voice notes arrive as audio/*, GIFs as image/gif — both first-class.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function sendAttachment(Conversation $conversation, User $sender, \Illuminate\Http\UploadedFile $file, ?string $body = null, ?string $clientMessageId = null): Message
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('Upload failed.');
        }
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        $type = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };
        $maxBytes = match ($type) {
            'video' => 50 * 1024 * 1024,
            'audio' => 25 * 1024 * 1024,
            'image' => 10 * 1024 * 1024,
            default => 10 * 1024 * 1024,
        };
        $allowed = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/quicktime', 'video/webm',
            'audio/mpeg', 'audio/ogg', 'audio/mp4', 'audio/wav', 'audio/webm',
            'application/pdf',
        ];
        if ($size > $maxBytes || ! in_array($mime, $allowed, true)) {
            throw new \InvalidArgumentException('File type or size not allowed.');
        }
        // Extension must agree with real content (no spoofed executables).
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'js', 'html', 'htm', 'svg'], true)) {
            throw new \InvalidArgumentException('File type not allowed.');
        }

        $path = $file->store("chat-attachments/{$conversation->id}", 'public');
        $meta = [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'file_size' => $size,
        ];
        if ($type === 'image' && ($dims = @getimagesize($file->getRealPath()))) {
            $meta['width'] = $dims[0];
            $meta['height'] = $dims[1];
        }

        return $this->sendMessage($conversation, $sender, [
            'body' => $body ?? '',
            'type' => $type,
            'attachments' => [$meta],
        ], $clientMessageId);
    }

    public function react(Message $message, User $user, string $emoji): MessageReaction
    {
        if (! $message->conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }

        return MessageReaction::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $user->id, 'emoji' => mb_substr($emoji, 0, 20)],
            []
        );
    }

    public function unreact(Message $message, User $user, string $emoji): bool
    {
        return (bool) MessageReaction::where('message_id', $message->id)->where('user_id', $user->id)->where('emoji', $emoji)->delete();
    }

    public function markRead(Conversation $conversation, User $user): int
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }

        return DB::transaction(function () use ($conversation, $user) {
            $unread = Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $user->id)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
                ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))
                ->get();
            foreach ($unread as $m) {
                $m->markReadBy($user);
                event(new MessageReadEvent($m, $user));
            }
            ConversationMember::where('conversation_id', $conversation->id)->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);

            return $unread->count();
        });
    }

    public function unreadCount(Conversation $conversation, User $user): int
    {
        $member = ConversationMember::where('conversation_id', $conversation->id)->where('user_id', $user->id)->first();
        $since = $member?->last_read_at;

        return Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    public function typing(Conversation $conversation, User $user, bool $isTyping = true): void
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }
        broadcast(new TypingIndicator($conversation, $user, $isTyping))->toOthers();
    }

    public function search(Conversation $conversation, User $user, string $keyword, int $limit = 20)
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }

        return Message::where('conversation_id', $conversation->id)
            ->where('body', 'like', '%'.$keyword.'%')
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))
            ->latest('id')->limit($limit)->get();
    }

    public function setting(Conversation $conversation, User $user, string $key, mixed $value): ConversationUserSetting
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }
        $allowed = ['is_muted', 'is_pinned', 'is_archived', 'theme', 'nickname'];
        if (! in_array($key, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid setting.');
        }
        $s = ConversationUserSetting::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id]
        );
        $s->update([$key => $value]);

        return $s->fresh();
    }

    public function unreadTotal(User $user): int
    {
        return Message::whereIn('conversation_id', Conversation::forUser($user->id)->pluck('id'))
            ->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    public function markAllRead(User $user): int
    {
        $conversations = Conversation::forUser($user->id)->pluck('id');
        $messages = Message::whereIn('conversation_id', $conversations)
            ->where('sender_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))
            ->get();
        foreach ($messages as $m) {
            $m->markReadBy($user);
        }
        ConversationMember::where('user_id', $user->id)->whereIn('conversation_id', $conversations)
            ->update(['last_read_at' => now()]);

        return $messages->count();
    }
}
