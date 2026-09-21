<?php

namespace App\Models;

use App\Enums\AiMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VirtualProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'ai_personality_id', 'mode', 'persona_prompt', 'greeting_message',
        'reply_templates', 'reply_delay_min_seconds', 'reply_delay_max_seconds', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mode' => AiMode::class,
            'reply_templates' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personality(): BelongsTo
    {
        return $this->belongsTo(AiPersonality::class, 'ai_personality_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(VirtualConversation::class);
    }

    public function replyDelaySeconds(): int
    {
        return random_int($this->reply_delay_min_seconds, max($this->reply_delay_min_seconds, $this->reply_delay_max_seconds));
    }
}
