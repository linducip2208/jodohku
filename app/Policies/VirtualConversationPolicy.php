<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VirtualConversation;

class VirtualConversationPolicy
{
    public function view(User $user, VirtualConversation $vc): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return (int) $vc->real_user_id === (int) $user->id;
    }

    public function operate(User $user, VirtualConversation $vc): bool
    {
        return in_array($user->role, [UserRole::Operator, UserRole::Admin, UserRole::Superadmin], true);
    }
}
