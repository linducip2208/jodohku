<?php

namespace App\Listeners;

use App\Events\PaymentPaid;
use App\Events\SubscriptionActivated;
use App\Events\UserRegistered;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Events\Dispatcher;

/**
 * Referral + affiliate fan-in. Attribution happens at registration
 * (ref code from query/session); conversion + commission fire exactly
 * once on paid events (row-locked, idempotent).
 */
class ReferralListener
{
    public function __construct(protected ReferralService $referrals) {}

    public function handleRegistered(UserRegistered $e): void
    {
        try {
            $code = request()->input('ref');
            if (! $code && session()->has('registration_ref')) {
                $code = session()->pull('registration_ref');
            }
            $this->referrals->attributeNewUser($e->user, $code);
        } catch (\Throwable) {
        }
    }

    public function handleSubscription(SubscriptionActivated $e): void
    {
        try {
            $this->referrals->convertOnSubscription($e->user);
        } catch (\Throwable) {
        }
    }

    public function handlePayment(PaymentPaid $e): void
    {
        try {
            $user = $e->payment->user ?? User::find($e->payment->user_id);
            if ($user) {
                $this->referrals->commissionOnPayment($user, $e->payment);
            }
        } catch (\Throwable) {
        }
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(UserRegistered::class, [self::class, 'handleRegistered']);
        $events->listen(SubscriptionActivated::class, [self::class, 'handleSubscription']);
        $events->listen(PaymentPaid::class, [self::class, 'handlePayment']);
    }
}
