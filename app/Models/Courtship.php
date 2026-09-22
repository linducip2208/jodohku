<?php

namespace App\Models;

use App\Enums\CourtshipStage;
use App\Enums\CourtshipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Courtship extends Model
{
    use HasFactory;

    protected $fillable = [
        'initiator_id', 'partner_id', 'match_id', 'conversation_id',
        'stage', 'status', 'guardian_name', 'guardian_phone', 'guardian_relation',
        'guardian_approved_at', 'stage_history', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => CourtshipStage::class,
            'status' => CourtshipStatus::class,
            'stage_history' => 'array',
            'guardian_approved_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(UserMatch::class, 'match_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function involves(int $userId): bool
    {
        return (int) $this->initiator_id === $userId || (int) $this->partner_id === $userId;
    }
}
