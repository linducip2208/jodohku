<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return (int) $report->reporter_id === (int) $user->id || $user->isStaff();
    }

    public function resolve(User $user, Report $report): bool
    {
        return in_array($user->role, [UserRole::Moderator, UserRole::Admin, UserRole::Superadmin], true);
    }
}
