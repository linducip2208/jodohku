<?php

namespace App\Models;

use App\Enums\ScheduledMessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'sender_id', 'body', 'type', 'client_message_id',
        'send_at', 'status', 'message_id', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScheduledMessageStatus::class,
            'send_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
