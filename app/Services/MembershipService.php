<?php

namespace App\Services;

use App\Models\MembershipPlan;
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
}
