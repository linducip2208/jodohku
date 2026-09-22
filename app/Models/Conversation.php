<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'type', 'title', 'match_id', 'created_by',
        'is_pinned', 'is_archived', 'is_blocked', 'last_message_at',
        'disappears_in_seconds',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'is_pinned' => 'boolean',
            'is_archived' => 'boolean',
            'is_blocked' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            $conversation->ulid ??= (string) Str::ulid();
            $conversation->type ??= ConversationType::Direct;
        });
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessages(): HasMany
    {
        return $this->hasMany(Message::class)->latest('id');
    }

    public function userMatch(): BelongsTo
    {
        return $this->belongsTo(UserMatch::class, 'match_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(ConversationLabel::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(ConversationUserSetting::class);
    }

    /** @param Builder<Conversation> $query */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('members', fn ($q) => $q->where('user_id', $userId)->whereNull('left_at'));
    }

    /** @param Builder<Conversation> $query */
    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('type', ConversationType::Direct);
    }

    public function otherMember(int $userId): ?ConversationMember
    {
        if ($this->relationLoaded('members')) {
            return $this->members->first(fn (ConversationMember $m) => $m->user_id !== $userId);
        }

        return $this->members()->where('user_id', '!=', $userId)->first();
    }

    public function otherUser(int $userId): ?User
    {
        return $this->otherMember($userId)?->user;
    }

    public function involves(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->whereNull('left_at')->exists();
    }

    public static function findDirect(int $userA, int $userB): ?self
    {
        return static::where('type', ConversationType::Direct)
            ->whereHas('members', fn ($q) => $q->where('user_id', $userA))
            ->whereHas('members', fn ($q) => $q->where('user_id', $userB))
            ->first();
    }
}
