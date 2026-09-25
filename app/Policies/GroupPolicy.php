<?php

namespace App\Policies;

use App\Enums\PrivacyVisibility;
use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(?User $viewer): bool
    {
        return true;
    }

    public function view(?User $viewer, Group $group): bool
    {
        $vis = $group->visibility instanceof PrivacyVisibility ? $group->visibility : PrivacyVisibility::tryFrom((string) $group->visibility);
        if ($vis === PrivacyVisibility::Public) {
            return true;
        }
        if (! $viewer) {
            return false;
        }
        if ($viewer->isStaff() || (int) $group->owner_id === (int) $viewer->id) {
            return true;
        }
        if ($vis === PrivacyVisibility::MembersOnly) {
            return $group->hasMember((int) $viewer->id) || $group->isManager((int) $viewer->id);
        }

        return $group->hasMember((int) $viewer->id);
    }

    public function manage(User $user, Group $group): bool
    {
        return $group->isManager((int) $user->id) || $user->isStaff();
    }

    public function join(User $user, Group $group): bool
    {
        $vis = $group->visibility instanceof PrivacyVisibility ? $group->visibility : PrivacyVisibility::tryFrom((string) $group->visibility);

        return in_array($vis, [PrivacyVisibility::Public, PrivacyVisibility::MembersOnly], true)
            && ! $group->hasMember((int) $user->id);
    }
}
