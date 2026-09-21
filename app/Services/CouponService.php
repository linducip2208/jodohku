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
        $coupon->redemptions()->create([
            'user_id' => $user->id,
            'payment_id' => $paymentId,
            'discount_amount' => $discount,
        ]);
        $coupon->increment('used_count');
        $this->audit->log('coupon.redeemed', $user, $coupon, [], ['payment_id' => $paymentId, 'discount' => $discount]);
    }
}
