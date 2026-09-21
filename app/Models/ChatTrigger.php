<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatTrigger extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'event', 'conditions', 'priority', 'cooldown_minutes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'is_active' => 'boolean'];
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ChatTriggerAction::class)->orderBy('sort_order');
    }

    /** @param Builder<ChatTrigger> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderByDesc('priority');
    }

    /** @param Builder<ChatTrigger> $query */
    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }
}
