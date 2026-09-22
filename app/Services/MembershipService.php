<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Support\Collection;

class MembershipService
{
    public function plans(): Collection
    {
        return MembershipPlan::active()->get();
    }

    public function find(string $code): ?MembershipPlan
    {
        return MembershipPlan::where('code', $code)->where('is_active', true)->first();
    }

    public function findOrFail(string $code): MembershipPlan
    {
        return MembershipPlan::where('code', $code)->where('is_active', true)->firstOrFail();
    }

    /** Feature entitlement matrix for the frontend comparison table. */
    public function featureMatrix(): Collection
    {
        return $this->plans()->map(function (MembershipPlan $plan) {
            return [
                'code' => $plan->code,
                'name' => $plan->name,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'daily_likes_limit' => $plan->daily_likes_limit,
                'monthly_super_likes' => $plan->monthly_super_likes,
                'monthly_boosts' => $plan->monthly_boosts,
                'has_read_receipts' => (bool) $plan->has_read_receipts,
                'has_incognito' => (bool) $plan->has_incognito,
                'features' => $plan->features ?? [],
            ];
        });
    }

    public function currentFeatures(User $user): array
    {
        $plan = $user->activeSubscription()?->plan;
        if (! $plan) {
            return [
                'daily_likes_limit' => config('jodohku.free_daily_likes', 20),
                'has_read_receipts' => false,
                'has_incognito' => false,
            ];
        }

        return [
            'daily_likes_limit' => $plan->daily_likes_limit,
            'has_read_receipts' => (bool) $plan->has_read_receipts,
            'has_incognito' => (bool) $plan->has_incognito,
        ];
    }
}
