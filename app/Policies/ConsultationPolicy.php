<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    public function view(User $user, Consultation $consultation): bool
    {
        return (int) $consultation->user_id === (int) $user->id
            || (int) $consultation->counselor?->user_id === (int) $user->id
            || $user->isStaff();
    }

    public function manage(User $user, Consultation $consultation): bool
    {
        return (int) $consultation->user_id === (int) $user->id
            || (int) $consultation->counselor?->user_id === (int) $user->id;
    }
}
