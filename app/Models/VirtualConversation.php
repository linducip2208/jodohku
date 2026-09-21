<?php

namespace App\Models;

use App\Enums\AiMode;
use App\Enums\VirtualConversationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'virtual_profile_id', 'real_user_id',
        'operator_id', 'mode', 'status', 'handed_over_at', 'ai_paused_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => AiMode::class,
            'status' => VirtualConversationStatus::class,
            'handed_over_at' => 'datetime',
            'ai_paused_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function virtualProfile(): BelongsTo
    {
        return $this->belongsTo(VirtualProfile::class);
    }

    public function realUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'real_user_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** @param Builder<VirtualConversation> $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            VirtualConversationStatus::Active->value,
            VirtualConversationStatus::Paused->value,
            VirtualConversationStatus::Escalated->value,
        ]);
    }

    public function handOverTo(User $operator): bool
    {
        return $this->update([
            'operator_id' => $operator->id,
            'mode' => AiMode::Template,
            'status' => VirtualConversationStatus::Transferred,
            'handed_over_at' => now(),
        ]);
    }
}
