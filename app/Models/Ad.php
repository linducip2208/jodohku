<?php

namespace App\Models;

use App\Enums\AdStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ad extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'body', 'image_path', 'target_url', 'placement', 'status',
        'starts_at', 'ends_at', 'budget', 'impressions_count', 'clicks_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(AdImpression::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AdClick::class);
    }

    /** @param Builder<Ad> $query */
    public function scopeServable(Builder $query): Builder
    {
        return $query->where('status', AdStatus::Active)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function ctr(): float
    {
        if ($this->impressions_count === 0) {
            return 0.0;
        }

        return round($this->clicks_count / $this->impressions_count * 100, 2);
    }
}
