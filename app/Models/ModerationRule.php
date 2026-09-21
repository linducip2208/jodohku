<?php

namespace App\Models;

use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModerationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'trigger_type', 'trigger_value',
        'action', 'threshold', 'window_minutes', 'is_active', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'action' => ModerationAction::class,
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<ModerationRule> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderByDesc('priority');
    }
}
