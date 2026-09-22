<?php

namespace App\Policies;

use App\Models\CompatibilityReport;
use App\Models\User;

class CompatibilityReportPolicy
{
    public function view(User $user, CompatibilityReport $report): bool
    {
        return (int) $report->user_id === (int) $user->id || $user->isStaff();
    }
}
