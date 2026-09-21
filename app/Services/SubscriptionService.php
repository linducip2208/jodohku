<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionActivated;
use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(protected AuditService $audit) {}

    public function activate(User $user, MembershipPlan $plan, array $opts = []): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $opts) {
            // Expire overlapping actives
            Subscription::where('user_id', $user->id)
                ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
                ->update(['status' => SubscriptionStatus::Expired->value]);

            $now = now();
            $days = $plan->duration_days ?: 30;
            $trialDays = (int) ($opts['trial_days'] ?? 0);

            $sub = Subscription::create([
                'user_id' => $user->id,
                'membership_plan_id' => $plan->id,
                'status' => $trialDays > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays($days),
                'trial_ends_at' => $trialDays > 0 ? $now->copy()->addDays($trialDays) : null,
                'auto_renew' => $opts['auto_renew'] ?? true,
            ]);

            $user->update(['is_premium' => true]);
            $this->audit->log('subscription.activated', $user, $sub, [], ['plan' => $plan->code]);
            event(new SubscriptionActivated($user, $sub->fresh()));

            return $sub->fresh();
        });
    }

    public function expire(Subscription $subscription): bool
    {
        return DB::transaction(function () use ($subscription) {
            $ok = $subscription->update(['status' => SubscriptionStatus::Expired]);
            $this->syncPremiumFlag($subscription->user);
            $this->audit->log('subscription.expired', null, $subscription);

            return $ok;
        });
    }

    public function cancel(Subscription $subscription, bool $immediate = false): bool
    {
        return DB::transaction(function () use ($subscription, $immediate) {
            $ok = $subscription->cancel($immediate);
            if ($immediate) {
                $this->syncPremiumFlag($subscription->user);
            }
            $this->audit->log('subscription.cancelled', $subscription->user, $subscription);

            return $ok;
        });
    }

    public function expireDue(int $batch = 200): int
    {
        $count = 0;
        Subscription::whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->where('ends_at', '<=', now())
            ->limit($batch)->get()->each(function (Subscription $s) use (&$count) {
                $this->expire($s);
                $count++;
            });

        return $count;
    }

    protected function syncPremiumFlag(User $user): void
    {
        $hasValid = Subscription::where('user_id', $user->id)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->exists();
        $user->update(['is_premium' => $hasValid]);
    }
}
