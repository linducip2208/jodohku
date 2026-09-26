<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MembershipService
{
    /**
     * Plans for the active brand (brand's own when defined, else global).
     * Brand plans use globally-unique codes (prefix per brand).
     */
    public function plans(): Collection
    {
        return $this->scoped()->get();
    }

    public function find(string $code): ?MembershipPlan
    {
        return $this->scoped()->where('code', $code)->first();
    }

    public function findOrFail(string $code): MembershipPlan
    {
        return $this->scoped()->where('code', $code)->firstOrFail();
    }

    /** Brand-owned plans win entirely when defined; else globals. */
    protected function scoped(): Builder
    {
        $brandId = $this->currentBrandId();
        $query = MembershipPlan::where('is_active', true);
        if ($brandId !== null && MembershipPlan::where('brand_id', $brandId)->where('is_active', true)->exists()) {
            return $query->where('brand_id', $brandId);
        }

        return $query->whereNull('brand_id');
    }

    protected function currentBrandId(): ?int
    {
        try {
            return app(BrandService::class)->current()?->id;
        } catch (\Throwable) {
            return null;
        }
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
                'daily_likes_limit' => (int) config('jodohku.limits.free_daily_likes', 20),
                'monthly_super_likes' => 0,
                'monthly_boosts' => 0,
                'has_read_receipts' => false,
                'has_incognito' => false,
            ];
        }

        return [
            'daily_likes_limit' => $plan->daily_likes_limit,
            'monthly_super_likes' => $plan->monthly_super_likes,
            'monthly_boosts' => $plan->monthly_boosts,
            'has_read_receipts' => (bool) $plan->has_read_receipts,
            'has_incognito' => (bool) $plan->has_incognito,
            'features' => $plan->features ?? [],
        ];
    }
}
