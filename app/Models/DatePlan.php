<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatePlan extends Model
{
    use HasFactory;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'proposer_id', 'partner_id', 'conversation_id',
        'scheduled_at', 'place', 'note', 'status', 'reminded_at',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function involves(int $userId): bool
    {
        return (int) $this->proposer_id === $userId || (int) $this->partner_id === $userId;
    }
}
