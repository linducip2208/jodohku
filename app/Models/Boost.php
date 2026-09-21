<?php

namespace App\Models;

use App\Enums\BoostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Boost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'status', 'duration_minutes', 'starts_at', 'ends_at',
        'views_gained', 'likes_gained',
    ];

    protected function casts(): array
    {
        return [
            'status' => BoostStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<Boost> $query */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', BoostStatus::Active)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function isLive(): bool
    {
        return $this->status === BoostStatus::Active
            && $this->starts_at?->lte(now())
            && $this->ends_at?->gt(now());
    }

    public function activate(int $durationMinutes = 30): bool
    {
        $now = now();

        return $this->update([
            'status' => BoostStatus::Active,
            'duration_minutes' => $durationMinutes,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addMinutes($durationMinutes),
        ]);
    }
}
