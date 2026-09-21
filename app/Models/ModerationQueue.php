<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModerationQueue extends Model
{
    use HasFactory;

    protected $table = 'moderation_queue';

    protected $fillable = [
        'queueable_type', 'queueable_id', 'reported_by', 'reason',
        'priority', 'status', 'assigned_to', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function queueable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'queueable_type', 'queueable_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @param Builder<ModerationQueue> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')->orderByDesc('priority')->orderBy('created_at');
    }
}
