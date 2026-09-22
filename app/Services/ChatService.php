<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ConversationType;
use App\Enums\MessageStatus;
use App\Enums\ScheduledMessageStatus;
use App\Events\MessageRead as MessageReadEvent;
use App\Events\MessageReceived;
use App\Events\MessageSent;
use App\Events\TypingIndicator;
use App\Exceptions\ChatQuotaExceededException;
use App\Jobs\ProcessMessageModeration;
use App\Jobs\SendChatNotification;
use App\Models\Block;
use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\ConversationLabel;
use App\Models\ConversationMember;
use App\Models\ConversationUserSetting;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageDeletion;
use App\Models\MessageReaction;
use App\Models\PollVote;
use App\Models\ScheduledMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserMatch;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Per-peer message quota: free members get a small allowance per counterpart
     * user, premium members get a larger one. Configurable via settings
     * (group `chat`: free_messages_per_peer / premium_messages_per_peer).
     */
    public function messageLimitFor(User $sender): int
    {
        return $sender->isPremium()
            ? max(1, (int) Setting::get('premium_messages_per_peer', 30, 'chat'))
            : max(1, (int) Setting::get('free_messages_per_peer', 1, 'chat'));
    }

    /** Messages the sender already sent to the peer across their shared conversations. */
    public function sentToPeerCount(int $senderId, int $peerId): int
    {
        $mine = ConversationMember::where('user_id', $senderId)->pluck('conversation_id');
        $theirs = ConversationMember::where('user_id', $peerId)->pluck('conversation_id');
        $shared = $mine->intersect($theirs);
        if ($shared->isEmpty()) {
            return 0;
        }

        return Message::where('sender_id', $senderId)->whereIn('conversation_id', $shared)->count();
    }

    public function messageQuotaRemaining(User $sender, User $peer): array
    {
        $limit = $this->messageLimitFor($sender);
        $used = $this->sentToPeerCount((int) $sender->id, (int) $peer->id);

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'is_premium' => (bool) $sender->isPremium(),
        ];
    }

    protected function quotaApplies(User $sender): bool
    {
        if ($sender->isStaff()) {
            return false;
        }
        $type = $sender->account_type;

        return $type instanceof AccountType ? $type->isHuman() : $type === null || $type === 'real';
    }

    protected function enforcePeerQuota(Conversation $conversation, User $sender): void
    {
        if (! $this->quotaApplies($sender)) {
            return;
        }
        $other = $conversation->relationLoaded('members')
            ? $conversation->members->first(fn ($m) => (int) $m->user_id !== (int) $sender->id && ($m->role ?? 'member') !== 'chaperone')
            : ConversationMember::where('conversation_id', $conversation->id)->where('user_id', '!=', (int) $sender->id)
                ->where(fn ($q) => $q->where('role', '!=', 'chaperone')->orWhereNull('role'))->first();
        if (! $other) {
            $other = $conversation->otherMember((int) $sender->id);
        }
        if (! $other || ! $other->user) {
            return;
        }
        $limit = $this->messageLimitFor($sender);
        $used = $this->sentToPeerCount((int) $sender->id, (int) $other->user->id);
        if ($used >= $limit) {
            throw new ChatQuotaExceededException($limit, (bool) $sender->isPremium());
        }
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
     * @param  array{body?:string,type?:string,reply_to_id?:int,attachments?:array,metadata?:array}  $data
     */
    public function sendMessage(Conversation $conversation, User $sender, array $data, ?string $clientMessageId = null): Message
    {
        if (! $conversation->involves((int) $sender->id)) {
            throw new \RuntimeException('Not a conversation member.');
        }
        $myMembership = $conversation->relationLoaded('members')
            ? $conversation->members->firstWhere('user_id', (int) $sender->id)
            : ConversationMember::where('conversation_id', $conversation->id)->where('user_id', $sender->id)->first();
        if ($myMembership && ($myMembership->role ?? 'member') === 'chaperone') {
            throw new \RuntimeException('Chaperones can only read, not send.');
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
        $data = $this->validateSpecialType($data);

        // Idempotency on (conversation_id, client_message_id)
        $clientId = $clientMessageId ?? (string) ($data['client_message_id'] ?? (string) Str::uuid());
        $existing = Message::where('conversation_id', $conversation->id)->where('client_message_id', $clientId)->first();
        if ($existing) {
            return $existing;
        }
        $this->enforcePeerQuota($conversation, $sender);

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
                'status' => MessageStatus::Sent,
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
            SendChatNotification::dispatch($message->id);

            // Queue AI review only when flagged
            if ($mod['needs_ai']) {
                ProcessMessageModeration::dispatch($message->id);
            }

            return $message->fresh();
        });
    }

    public function editMessage(Message $message, User $user, string $newBody): Message
    {
        if ((int) $message->sender_id !== (int) $user->id) {
            throw new \RuntimeException('Only sender can edit.');
        }
        // Edit window: default 15 minutes, configurable via settings group `chat`.
        $window = max(0, (int) Setting::get('edit_window_minutes', 15, 'chat'));
        if ($window > 0 && $message->created_at && $message->created_at->diffInMinutes(now()) > $window) {
            throw new \RuntimeException('Edit window expired.');
        }
        if (in_array($message->status?->value, ['deleted', 'moderated'], true)) {
            throw new \RuntimeException('Message cannot be edited.');
        }
        $mod = $this->moderation->moderate($newBody, $user, $message);
        if ($mod['decision'] === 'block') {
            throw new \RuntimeException('Edited message blocked by moderation.');
        }
        $meta = $message->metadata ?? [];
        $history = $meta['edit_history'] ?? [];
        $history[] = ['body' => $message->body, 'at' => now()->toDateTimeString(), 'by' => $user->id];
        $meta['edit_history'] = array_slice($history, -10);
        $meta['moderation'] = ['risk' => $mod['risk'], 'decision' => $mod['decision'], 'flags' => $mod['flags']];
        $message->update(['body' => $mod['clean'], 'is_edited' => true, 'metadata' => $meta]);
        $fresh = $message->fresh();
        // Reuse existing broadcast channel so the peer sees the edit in realtime.
        try {
            event(new MessageSent($fresh));
        } catch (\Throwable) {
        }

        return $fresh;
    }

    /** Soft delete per user; for_everyone only by sender (redacts body + removes files). */
    public function deleteMessage(Message $message, User $user, string $scope = 'for_me'): bool
    {
        if ($scope === 'for_everyone' && (int) $message->sender_id !== (int) $user->id) {
            throw new \RuntimeException('Only sender can delete for everyone.');
        }
        if ($scope === 'for_everyone') {
            $message->update(['status' => MessageStatus::Deleted, 'body' => '[deleted]']);
            try {
                foreach ($message->attachments as $att) {
                    if (! empty($att->file_path)) {
                        Storage::disk((string) config('chat.attachments.disk', 'chat'))->delete($att->file_path);
                    }
                    $att->delete();
                }
            } catch (\Throwable) {
            }
            try {
                event(new MessageSent($message->fresh()));
            } catch (\Throwable) {
            }
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
    public function sendAttachment(Conversation $conversation, User $sender, UploadedFile $file, ?string $body = null, ?string $clientMessageId = null): Message
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

        $disk = (string) config('chat.attachments.disk', 'chat');
        $path = $file->store("chat-attachments/{$conversation->id}", $disk);
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
            ->whereRaw('LOWER(body) LIKE ?', ['%'.strtolower($keyword).'%'])
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

    /** Scoped mark-read for ONE conversation (used by the per-conversation endpoint). */
    public function markConversationRead(Conversation $conversation, User $user): int
    {
        return $this->markRead($conversation, $user);
    }

    /** Aggregate inbox statistics for a user. */
    public function overview(User $user): array
    {
        $convIds = Conversation::forUser($user->id)->pluck('id');
        $settings = ConversationUserSetting::where('user_id', $user->id)->whereIn('conversation_id', $convIds)->get();

        return [
            'total_conversations' => $convIds->count(),
            'unread_total' => $this->unreadTotal($user),
            'pinned' => $settings->where('is_pinned', true)->count(),
            'muted' => $settings->where('is_muted', true)->count(),
            'archived' => $settings->where('is_archived', true)->count(),
            'with_attachments' => Message::whereIn('conversation_id', $convIds)->whereHas('attachments')->distinct('conversation_id')->count('conversation_id'),
            'labels' => ConversationLabel::whereIn('conversation_id', $convIds)
                ->selectRaw('label, COUNT(*) as total')->groupBy('label')->orderByDesc('total')->limit(5)->pluck('total', 'label'),
        ];
    }

    /** Search a user's own conversations by keyword (message body or peer name). */
    public function searchConversations(User $user, string $keyword, int $limit = 10): array
    {
        $convIds = Conversation::forUser($user->id)->pluck('id');
        $kw = strtolower(trim($keyword));
        if ($kw === '') {
            return [];
        }

        $messages = Message::whereIn('conversation_id', $convIds)
            ->whereRaw('LOWER(body) LIKE ?', ["%{$kw}%"])
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))
            ->with('sender')->latest('id')->limit($limit)->get()
            ->groupBy('conversation_id');

        $conversations = Conversation::whereIn('id', $messages->keys())->with(['members.user'])->get()->keyBy('id');

        return $messages->map(function ($items, $convId) use ($conversations) {
            $conv = $conversations->get($convId);

            return [
                'conversation_id' => (int) $convId,
                'title' => $conv?->title,
                'matches' => $items->take(3)->map(fn ($m) => [
                    'message_id' => $m->id,
                    'sender_name' => $m->sender?->display_name ?? $m->sender?->name,
                    'body' => mb_substr((string) $m->body, 0, 160),
                    'created_at' => $m->created_at,
                ])->values(),
            ];
        })->values()->all();
    }

    public function conversationStats(Conversation $conversation, User $user): array
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }
        $messages = Message::where('conversation_id', $conversation->id)->get();
        $mine = $messages->where('sender_id', $user->id);
        $theirs = $messages->where('sender_id', '!=', $user->id);
        $createdDates = $messages->map(fn ($m) => $m->created_at?->toDateString())->filter();

        return [
            'conversation_id' => $conversation->id,
            'total_messages' => $messages->count(),
            'my_messages' => $mine->count(),
            'their_messages' => $theirs->count(),
            'attachments' => MessageAttachment::whereIn('message_id', $messages->pluck('id'))->count(),
            'reactions' => MessageReaction::whereIn('message_id', $messages->pluck('id'))->count(),
            'first_message_at' => $messages->min('created_at'),
            'last_message_at' => $messages->max('created_at'),
            'active_days' => $createdDates->unique()->count(),
        ];
    }

    /** Soft-delete every message for the given user (clears their thread view). */
    public function clearHistory(Conversation $conversation, User $user): int
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }
        $ids = Message::where('conversation_id', $conversation->id)
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))->pluck('id');

        return DB::transaction(function () use ($conversation, $user, $ids) {
            foreach ($ids as $id) {
                MessageDeletion::firstOrCreate(['message_id' => $id, 'user_id' => $user->id, 'scope' => 'for_me']);
            }
            ConversationMember::where('conversation_id', $conversation->id)->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);

            return $ids->count();
        });
    }

    /** Conversation media gallery: paginated attachments without scanning all messages. */
    public function gallery(Conversation $conversation, User $user, ?string $type = null, int $perPage = 20, ?int $cursor = null): array
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }
        $perPage = max(1, min(50, $perPage));
        $query = MessageAttachment::whereHas('message', fn ($q) => $q
            ->where('conversation_id', $conversation->id)
            ->whereNotIn('status', ['deleted', 'moderated'])
            ->whereDoesntHave('deletions', fn ($qq) => $qq->where('user_id', $user->id)->where('scope', 'for_me')))
            ->with(['message:id,conversation_id,sender_id,body,type,created_at'])
            ->latest('id');
        if ($type) {
            $mimeMap = ['image' => 'image/%', 'video' => 'video/%', 'audio' => 'audio/%', 'file' => 'application/%', 'pdf' => 'application/pdf'];
            if (isset($mimeMap[$type])) {
                $query->where('mime_type', 'like', $mimeMap[$type]);
            }
        }
        if ($cursor) {
            $query->where('id', '<', $cursor);
        }
        $items = $query->limit($perPage + 1)->get();
        $hasMore = $items->count() > $perPage;
        $items = $items->take($perPage)->values();
        $nextCursor = $hasMore ? $items->last()?->id : null;

        return ['data' => $items, 'next_cursor' => $nextCursor, 'has_more' => $hasMore];
    }

    public function createConversation(User $a, User $b): Conversation
    {
        return $this->findOrCreateDirect($a, $b);
    }

    /** Built-in sticker catalog (unicode, no assets needed). */
    public function stickers(): array
    {
        return config('chat.stickers', []);
    }

    /** Chat theme presets usable via conversation settings (`theme` key). */
    public function themes(): array
    {
        return config('chat.themes', []);
    }

    protected function validateSpecialType(array $data): array
    {
        $type = $data['type'] ?? 'text';
        if ($type === 'sticker') {
            $allowed = collect($this->stickers())->pluck('emoji')->all();
            if (! in_array(trim((string) ($data['body'] ?? '')), $allowed, true)) {
                throw new \InvalidArgumentException('Unknown sticker.');
            }
        }
        if ($type === 'poll') {
            $options = $data['metadata']['options'] ?? null;
            if (! is_array($options) || count($options) < 2 || count($options) > 4) {
                throw new \InvalidArgumentException('Poll needs 2-4 options.');
            }
            $clean = [];
            foreach ($options as $opt) {
                $opt = trim(mb_substr((string) $opt, 0, 120));
                if ($opt === '') {
                    throw new \InvalidArgumentException('Poll options cannot be empty.');
                }
                $clean[] = $opt;
            }
            if (mb_strlen(trim((string) ($data['body'] ?? ''))) > 300) {
                throw new \InvalidArgumentException('Poll question too long.');
            }
            $data['metadata']['options'] = array_values($clean);
        }

        return $data;
    }

    /** Vote (or change vote) on a poll message. */
    public function votePoll(Message $message, User $user, int $optionIndex): array
    {
        if ($message->type !== 'poll') {
            throw new \InvalidArgumentException('Message is not a poll.');
        }
        if (! $message->conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }
        $options = $message->metadata['options'] ?? [];
        if (! isset($options[$optionIndex])) {
            throw new \InvalidArgumentException('Invalid option.');
        }
        PollVote::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $user->id],
            ['option_index' => $optionIndex]
        );

        return $this->pollResults($message, $user);
    }

    public function pollResults(Message $message, User $user): array
    {
        if ($message->type !== 'poll') {
            throw new \InvalidArgumentException('Message is not a poll.');
        }
        if (! $message->conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }
        $options = array_values($message->metadata['options'] ?? []);
        $counts = array_fill(0, count($options), 0);
        foreach (PollVote::where('message_id', $message->id)->get(['user_id', 'option_index']) as $vote) {
            if (isset($counts[$vote->option_index])) {
                $counts[$vote->option_index]++;
            }
        }
        $mine = PollVote::where('message_id', $message->id)->where('user_id', $user->id)->value('option_index');

        return [
            'message_id' => $message->id,
            'question' => $message->body,
            'options' => array_map(fn ($text, $i) => ['index' => $i, 'text' => $text, 'votes' => $counts[$i] ?? 0], $options, array_keys($options)),
            'total_votes' => array_sum($counts),
            'my_vote' => $mine === null ? null : (int) $mine,
        ];
    }

    /** CSV rendering of export() for download. */
    public function exportCsv(Conversation $conversation, User $user): string
    {
        $data = $this->export($conversation, $user);
        $lines = ['id,sent_at,sender_id,sender_name,type,body'];
        foreach ($data['messages'] as $m) {
            $lines[] = implode(',', [
                $m['id'],
                $m['created_at'],
                $m['sender_id'],
                '"'.str_replace('"', '""', (string) ($m['sender_name'] ?? '')).'"',
                $m['type'],
                '"'.str_replace('"', '""', preg_replace('/\s+/', ' ', (string) $m['body'])).'"',
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /** Disks searched for attachment files: private first, public legacy. */
    public function attachmentDisks(): array
    {
        return array_values(array_unique([(string) config('chat.attachments.disk', 'chat'), 'public']));
    }

    public function attachmentDiskFor(string $path): ?string
    {
        foreach ($this->attachmentDisks() as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return $disk;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** Hard-delete messages past the conversation disappearing-message TTL. */
    public function pruneDisappearing(int $batch = 200): int
    {
        $pruned = 0;
        $ttls = Conversation::whereNotNull('disappears_in_seconds')->pluck('disappears_in_seconds', 'id');
        foreach ($ttls as $convId => $ttl) {
            if ($pruned >= $batch) {
                break;
            }
            $ids = Message::where('conversation_id', $convId)
                ->where('created_at', '<=', now()->subSeconds(max(60, (int) $ttl)))
                ->limit($batch - $pruned)->pluck('id');
            if ($ids->isEmpty()) {
                continue;
            }
            $paths = MessageAttachment::whereIn('message_id', $ids)->pluck('file_path')->all();
            foreach ($paths as $path) {
                foreach ($this->attachmentDisks() as $disk) {
                    try {
                        Storage::disk($disk)->delete($path);
                    } catch (\Throwable) {
                    }
                }
            }
            MessageAttachment::whereIn('message_id', $ids)->delete();
            PollVote::whereIn('message_id', $ids)->delete();
            $pruned += Message::whereIn('id', $ids)->delete();
        }

        return $pruned;
    }

    /** Queue a message for future delivery; quota/moderation apply at dispatch time. */
    public function scheduleMessage(Conversation $conversation, User $sender, string $body, \DateTimeInterface $sendAt, ?string $clientMessageId = null): ScheduledMessage
    {
        if (! $conversation->involves((int) $sender->id)) {
            throw new \RuntimeException('Not a conversation member.');
        }
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Message body required.');
        }
        if (mb_strlen($body) > (int) config('chat.message.max_length', 2000)) {
            throw new \InvalidArgumentException('Message too long.');
        }
        $at = Carbon::parse($sendAt);
        if ($at->isPast()) {
            throw new \InvalidArgumentException('Schedule must be in the future.');
        }
        if ($at->gt(now()->addDays(30))) {
            throw new \InvalidArgumentException('Schedule too far ahead (max 30 days).');
        }

        return ScheduledMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => $body,
            'type' => 'text',
            'client_message_id' => $clientMessageId,
            'send_at' => $at,
            'status' => ScheduledMessageStatus::Pending,
        ]);
    }

    public function scheduledFor(Conversation $conversation, User $user, int $perPage = 20)
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a conversation member.');
        }

        return ScheduledMessage::where('conversation_id', $conversation->id)
            ->where('sender_id', $user->id)->latest('send_at')->paginate($perPage);
    }

    public function cancelScheduled(ScheduledMessage $scheduled, User $user): bool
    {
        if ((int) $scheduled->sender_id !== (int) $user->id && ! $user->isStaff()) {
            throw new \RuntimeException('Only sender can cancel.');
        }
        if ($scheduled->status !== ScheduledMessageStatus::Pending) {
            throw new \RuntimeException('Only pending messages can be cancelled.');
        }

        return (bool) $scheduled->update(['status' => ScheduledMessageStatus::Cancelled]);
    }

    /** Send all due scheduled messages. Returns [sent, failed]. */
    public function dispatchDue(int $batch = 100): array
    {
        $sent = 0;
        $failed = 0;
        // Requeue claims abandoned by crashed workers.
        ScheduledMessage::where('status', ScheduledMessageStatus::Sending)
            ->where('updated_at', '<', now()->subMinutes(10))->update(['status' => ScheduledMessageStatus::Pending]);
        $ids = ScheduledMessage::where('status', ScheduledMessageStatus::Pending)
            ->where('send_at', '<=', now())->limit($batch)->pluck('id');
        foreach ($ids as $id) {
            // Atomic per-row claim: concurrent dispatchers cannot double-send.
            $claimed = ScheduledMessage::where('id', $id)->where('status', ScheduledMessageStatus::Pending)
                ->update(['status' => ScheduledMessageStatus::Sending]);
            if (! $claimed) {
                continue;
            }
            $item = ScheduledMessage::find($id);
            if (! $item) {
                continue;
            }
            try {
                $conversation = $item->conversation;
                $sender = $item->sender;
                if (! $conversation || ! $sender) {
                    throw new \RuntimeException('Conversation or sender gone.');
                }
                $message = $this->sendMessage($conversation, $sender, [
                    'body' => $item->body,
                    'type' => $item->type,
                    'metadata' => ['scheduled_message_id' => $item->id],
                ], $item->client_message_id);
                $item->update(['status' => ScheduledMessageStatus::Sent, 'message_id' => $message->id]);
                $sent++;
            } catch (\Throwable $e) {
                $item->update(['status' => ScheduledMessageStatus::Failed, 'failure_reason' => mb_substr($e->getMessage(), 0, 500)]);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function export(Conversation $conversation, User $user): array
    {
        if (! $conversation->involves((int) $user->id)) {
            throw new \RuntimeException('Not a member.');
        }
        $messages = $conversation->messages()->with(['sender', 'attachments', 'reactions'])
            ->whereDoesntHave('deletions', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')->get();

        return [
            'conversation_id' => $conversation->id,
            'title' => $conversation->title,
            'type' => $conversation->type->value,
            'created_at' => $conversation->created_at,
            'total_messages' => $messages->count(),
            'members' => $conversation->members->pluck('user_id')->all(),
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'sender_id' => $m->sender_id,
                'sender_name' => $m->sender?->display_name ?? $m->sender?->name,
                'body' => $m->body,
                'type' => $m->type,
                'status' => $m->status->value ?? $m->status,
                'created_at' => $m->created_at,
                'is_edited' => $m->is_edited,
                'attachments' => $m->attachments->map(fn ($a) => ['file_path' => $a->file_path, 'file_name' => $a->file_name, 'mime_type' => $a->mime_type]),
                'reactions' => $m->reactions->map(fn ($r) => ['emoji' => $r->emoji, 'user_id' => $r->user_id]),
            ])->all(),
        ];
    }
}
