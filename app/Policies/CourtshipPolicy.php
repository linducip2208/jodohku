<?php

namespace App\Policies;

use App\Models\Courtship;
use App\Models\User;

class CourtshipPolicy
{
    public function view(User $user, Courtship $courtship): bool
    {
        return $courtship->involves((int) $user->id) || $user->isStaff();
    }

    public function manage(User $user, Courtship $courtship): bool
    {
        return $courtship->involves((int) $user->id);
    }
}
