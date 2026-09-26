<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id', 'code', 'name', 'description', 'price', 'currency', 'interval',
        'duration_days', 'features', 'daily_likes_limit', 'monthly_super_likes',
        'monthly_boosts', 'has_read_receipts', 'has_incognito', 'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'has_read_receipts' => 'boolean',
            'has_incognito' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Owning brand (null = global plan for all brands). */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @param Builder<MembershipPlan> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
