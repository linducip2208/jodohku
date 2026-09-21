<?php

namespace App\Models;

use App\Enums\ChatRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id', 'receiver_id', 'status', 'message', 'responded_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChatRequestStatus::class,
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /** @param Builder<ChatRequest> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ChatRequestStatus::Pending);
    }

    public function accept(): bool
    {
        return $this->update(['status' => ChatRequestStatus::Accepted, 'responded_at' => now()]);
    }

    public function decline(): bool
    {
        return $this->update(['status' => ChatRequestStatus::Declined, 'responded_at' => now()]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast()
            && $this->status === ChatRequestStatus::Pending;
    }
}
