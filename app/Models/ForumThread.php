<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ForumThread extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'forum_id', 'user_id', 'title', 'body',
        'is_pinned', 'is_locked', 'is_hidden', 'reply_count', 'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'is_hidden' => 'boolean',
            'last_reply_at' => 'datetime',
        ];
    }

    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'thread_id');
    }

    public function visibleReplies(): HasMany
    {
        return $this->replies()->where('is_hidden', false);
    }

    /** Append a reply; guards locked/hidden threads. Concurrency-safe counters. */
    public function addReply(User $user, string $body): ForumReply
    {
        if ($this->is_locked) {
            throw new \InvalidArgumentException('Thread is locked.');
        }
        if ($this->is_hidden) {
            throw new \InvalidArgumentException('Thread is hidden.');
        }

        return DB::transaction(function () use ($user, $body) {
            $reply = $this->replies()->create(['user_id' => $user->id, 'body' => $body]);
            $this->increment('reply_count');
            $this->update(['last_reply_at' => now()]);

            return $reply;
        });
    }
}
