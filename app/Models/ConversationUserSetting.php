<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationUserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'user_id', 'is_muted', 'is_pinned',
        'is_archived', 'theme', 'nickname',
    ];

    protected function casts(): array
    {
        return [
            'is_muted' => 'boolean',
            'is_pinned' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
