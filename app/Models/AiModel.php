<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    use HasFactory;

    protected $table = 'ai_models';

    protected $fillable = [
        'ai_provider_id', 'code', 'name', 'cost_per_1k_input',
        'cost_per_1k_output', 'max_tokens', 'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_1k_input' => 'decimal:6',
            'cost_per_1k_output' => 'decimal:6',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    /** @param Builder<AiModel> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function estimateCost(int $inputTokens, int $outputTokens): float
    {
        return ($inputTokens / 1000) * (float) $this->cost_per_1k_input
            + ($outputTokens / 1000) * (float) $this->cost_per_1k_output;
    }
}
