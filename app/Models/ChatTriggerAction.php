<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatTriggerAction extends Model
{
    use HasFactory;

    protected $fillable = ['chat_trigger_id', 'action_type', 'payload', 'sort_order'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(ChatTrigger::class, 'chat_trigger_id');
    }
}
