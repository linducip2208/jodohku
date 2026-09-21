<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return (int) $payment->user_id === (int) $user->id || $user->isStaff();
    }

    public function refund(User $user, Payment $payment): bool
    {
        return in_array($user->role, [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Superadmin], true);
    }
}
