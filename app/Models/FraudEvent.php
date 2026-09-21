<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'event_type', 'description', 'severity', 'metadata', 'ip_address', 'is_resolved',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'is_resolved' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<FraudEvent> $query */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    public function resolve(): bool
    {
        return $this->update(['is_resolved' => true]);
    }
}
