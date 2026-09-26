<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeedDatingRound extends Model
{
    protected $fillable = [
        'event_id', 'round_no', 'user_a_id', 'user_b_id',
        'conversation_id', 'starts_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function partnerA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    public function partnerB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
