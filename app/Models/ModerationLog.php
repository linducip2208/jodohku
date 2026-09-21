<?php

namespace App\Models;

use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'moderator_id', 'target_user_id', 'moderatable_type', 'moderatable_id',
        'action', 'reason', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'action' => ModerationAction::class,
            'metadata' => 'array',
        ];
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function moderatable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'moderatable_type', 'moderatable_id');
    }
}
