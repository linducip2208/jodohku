<?php

namespace App\Policies;

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
        return in_array($user->role, [\App\Enums\UserRole::Moderator, \App\Enums\UserRole::Admin, \App\Enums\UserRole::Superadmin], true);
    }
}
