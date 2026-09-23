<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;

class CouponService
{
    public function __construct(protected AuditService $audit) {}

    public function find(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper(trim($code)))->first();
    }

    /** Validate and return the discount amount without persisting anything. */
    public function quote(User $user, string $code, float $subtotal): array
    {
        $coupon = $this->find($code);
        if (! $coupon) {
            throw new \InvalidArgumentException('Coupon not found.');
        }
        // Re-fetch with row lock so concurrent checkouts serialize on the
        // coupon (usage_limit / per_user_limit enforced; caller must be in txn).
        $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->first();
        $discount = ($locked ?? $coupon)->quoteFor($user, $subtotal);

        return ['coupon' => $locked ?? $coupon, 'discount' => $discount];
    }

    public function recordRedemption(Coupon $coupon, User $user, int $paymentId, float $discount): void
    {
        // Idempotent per payment (unique coupon+payment) + re-verified under
        // lock: a coupon exhausted between quote and fulfill cannot overshoot.
        $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
        if ($locked->redemptions()->where('payment_id', $paymentId)->exists()) {
            return;
        }
        if (! $locked->isLive()) {
            throw new \InvalidArgumentException('Coupon is no longer active.');
        }
        if ($locked->usage_limit !== null && $locked->used_count >= $locked->usage_limit) {
            throw new \InvalidArgumentException('Coupon usage limit reached.');
        }
        if ($locked->redemptions()->where('user_id', $user->id)->count() >= (int) $locked->per_user_limit) {
            throw new \InvalidArgumentException('Coupon usage limit reached for this account.');
        }
        $locked->redemptions()->create([
            'user_id' => $user->id,
            'payment_id' => $paymentId,
            'discount_amount' => $discount,
        ]);
        $locked->increment('used_count');
        $this->audit->log('coupon.redeemed', $user, $locked, [], ['payment_id' => $paymentId, 'discount' => $discount]);
    }
}
