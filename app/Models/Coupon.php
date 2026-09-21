<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'value', 'max_discount', 'min_order',
        'usage_limit', 'used_count', 'per_user_limit',
        'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function userUsageCount(int $userId): int
    {
        return $this->redemptions()->where('user_id', $userId)->count();
    }

    /** Discount amount for a given order subtotal. Throws on any rule violation. */
    public function quoteFor(User $user, float $subtotal): float
    {
        if (! $this->isLive()) {
            throw new \InvalidArgumentException('Coupon is not active.');
        }
        if ($subtotal < (float) $this->min_order) {
            throw new \InvalidArgumentException('Order total does not meet coupon minimum.');
        }
        if ($this->userUsageCount((int) $user->id) >= (int) $this->per_user_limit) {
            throw new \InvalidArgumentException('Coupon usage limit reached for this account.');
        }

        $discount = $this->type === 'fixed'
            ? (float) $this->value
            : $subtotal * ((float) $this->value / 100);

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min(max($discount, 0), $subtotal), 2);
    }
}
