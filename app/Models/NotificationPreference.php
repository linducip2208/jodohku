<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'email_matches', 'email_messages', 'email_promotions',
        'push_matches', 'push_messages', 'push_likes', 'push_super_likes',
        'push_follows', 'push_comments', 'push_mentions', 'push_dates', 'sms_enabled',
    ];

    protected function casts(): array
    {
        return [
            'email_matches' => 'boolean',
            'email_messages' => 'boolean',
            'email_promotions' => 'boolean',
            'push_matches' => 'boolean',
            'push_messages' => 'boolean',
            'push_likes' => 'boolean',
            'push_super_likes' => 'boolean',
            'push_follows' => 'boolean',
            'push_comments' => 'boolean',
            'push_mentions' => 'boolean',
            'push_dates' => 'boolean',
            'sms_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaultsFor(int $userId): array
    {
        return [
            'user_id' => $userId,
            'email_matches' => true,
            'email_messages' => true,
            'email_promotions' => false,
            'push_matches' => true,
            'push_messages' => true,
            'push_likes' => true,
            'push_super_likes' => true,
            'push_follows' => true,
            'push_comments' => true,
            'push_mentions' => true,
            'push_dates' => true,
            'sms_enabled' => false,
        ];
    }
}
