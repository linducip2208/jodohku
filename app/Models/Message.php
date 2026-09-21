<?php

namespace App\Models;

use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'conversation_id', 'sender_id', 'body', 'type', 'status',
        'client_message_id', 'reply_to_id', 'is_edited', 'is_system',
        'is_ai_generated', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'is_edited' => 'boolean',
            'is_system' => 'boolean',
            'is_ai_generated' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            $message->ulid ??= (string) Str::ulid();
            $message->client_message_id ??= (string) Str::uuid();
            $message->status ??= MessageStatus::Sent;
        });

        static::created(function (Message $message) {
            $message->conversation()->update(['last_message_at' => $message->created_at ?? now()]);
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(MessageDeletion::class);
    }

    /** @param Builder<Message> $query */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNotIn('status', [MessageStatus::Deleted->value, MessageStatus::Moderated->value]);
    }

    public function isVisibleTo(int $userId): bool
    {
        if (in_array($this->status, [MessageStatus::Deleted, MessageStatus::Moderated], true)) {
            $deletion = $this->deletions->firstWhere('user_id', $userId)
                ?? $this->deletions()->where('user_id', $userId)->first();

            if ($deletion && $deletion->scope === 'for_everyone') {
                return false;
            }

            if ($this->status === MessageStatus::Moderated) {
                return false;
            }

            return $deletion === null || $deletion->scope !== 'for_me';
        }

        return ! $this->deletions()->where('user_id', $userId)->exists();
    }

    public function markReadBy(User $user): MessageRead
    {
        return MessageRead::firstOrCreate(
            ['message_id' => $this->id, 'user_id' => $user->id],
            ['read_at' => now()]
        );
    }
}
