<?php

namespace App\Services;

use App\Models\AffiliateAccount;
use App\Models\AffiliateCommission;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referrals (member invites) + affiliates (revenue share) with fraud
 * guards. Rewards pay in credits; affiliate payouts stay manual
 * (approved → paid by finance) to keep money movement auditable.
 */
class ReferralService
{
    public function codeFor(User $user): string
    {
        if (! $user->referral_code) {
            $user->update(['referral_code' => strtoupper(Str::random(8))]);
        }

        return (string) $user->fresh()->referral_code;
    }

    public function linkFor(User $user): string
    {
        return rtrim((string) config('app.url'), '/').'/register?ref='.$this->codeFor($user);
    }

    /** Attribute a fresh registration. Returns false when ineligible. */
    public function attributeNewUser(User $referred, ?string $code): bool
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return false;
        }
        $referrer = User::where('referral_code', $code)->first();
        if (! $referrer || (int) $referrer->id === (int) $referred->id) {
            return false;
        }
        // Fraud guard: cap conversions credited per referrer per day.
        $today = Referral::where('referrer_id', $referrer->id)->whereDate('created_at', today())->count();
        if ($today >= max(1, (int) config('referrals.daily_cap', 20))) {
            return false;
        }
        $row = Referral::firstOrCreate(
            ['referrer_id' => $referrer->id, 'referred_id' => $referred->id],
            ['status' => Referral::STATUS_REGISTERED]
        );

        return $row->wasRecentlyCreated;
    }

    /**
     * Called on referred user's first paid subscription. Idempotent:
     * only `registered` rows convert, exactly once.
     */
    public function convertOnSubscription(User $referred): void
    {
        $row = Referral::where('referred_id', $referred->id)->where('status', Referral::STATUS_REGISTERED)->first();
        if (! $row) {
            return;
        }
        DB::transaction(function () use ($row, $referred) {
            $locked = Referral::where('id', $row->id)->where('status', Referral::STATUS_REGISTERED)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }
            $reward = max(0, (int) config('referrals.reward_credits', 100));
            $locked->update(['status' => Referral::STATUS_CONVERTED, 'reward_credits' => $reward]);
            if ($reward > 0 && $locked->referrer) {
                app(CreditService::class)->award($locked->referrer, $reward, 'Referral conversion: user #'.$referred->id);
                $locked->update(['status' => Referral::STATUS_REWARDED]);
            }
        });
    }

    /** @return array{account:AffiliateAccount|null, stats:array} */
    public function dashboard(User $user): array
    {
        $account = AffiliateAccount::where('user_id', $user->id)->first();
        $refs = Referral::where('referrer_id', $user->id);
        $stats = [
            'link' => $this->linkFor($user),
            'referred' => (clone $refs)->count(),
            'converted' => (clone $refs)->whereIn('status', [Referral::STATUS_CONVERTED, Referral::STATUS_REWARDED])->count(),
            'rewards_earned' => (clone $refs)->sum('reward_credits'),
            'affiliate_balance' => $account?->balance() ?? 0.0,
        ];

        return ['account' => $account, 'stats' => $stats];
    }

    public function applyAffiliate(User $user): AffiliateAccount
    {
        return AffiliateAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['code' => 'AFF-'.strtoupper(Str::random(8)), 'status' => AffiliateAccount::STATUS_PENDING]
        );
    }

    /**
     * Record an affiliate commission for a paid payment. Idempotent per
     * payment (unique affiliate+payment pair enforced by lookup).
     */
    public function commissionOnPayment(User $payer, Payment $payment): void
    {
        $ref = Referral::where('referred_id', $payer->id)->first();
        if (! $ref) {
            return;
        }
        $account = AffiliateAccount::where('user_id', $ref->referrer_id)->where('status', AffiliateAccount::STATUS_APPROVED)->first();
        if (! $account) {
            return;
        }
        $exists = AffiliateCommission::where('affiliate_account_id', $account->id)->where('payment_id', $payment->id)->exists();
        if ($exists) {
            return;
        }
        $rate = (float) ($account->commission_rate ?: config('affiliates.default_rate', 0.10));
        AffiliateCommission::create([
            'affiliate_account_id' => $account->id,
            'payment_id' => $payment->id,
            'amount' => round((float) $payment->total_amount * $rate, 2),
            'status' => AffiliateCommission::STATUS_PENDING,
        ]);
    }
}
