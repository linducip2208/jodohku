<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'operator_id', 'conversation_id', 'virtual_profile_id',
        'assigned_at', 'released_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function virtualProfile(): BelongsTo
    {
        return $this->belongsTo(VirtualProfile::class);
    }

    /** @param Builder<OperatorAssignment> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('released_at');
    }

    public function release(): bool
    {
        return $this->update(['is_active' => false, 'released_at' => now()]);
    }
}
