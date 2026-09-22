<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Block;
use App\Models\User;

class UserPolicy
{
    public function viewAny(?User $viewer): bool
    {
        return true;
    }

    public function view(?User $viewer, User $model): bool
    {
        if (! $viewer) {
            return false;
        }
        if ($viewer->id === $model->id) {
            return true;
        }
        if ($viewer->isStaff()) {
            return true;
        }
        if (Block::existsBetween((int) $viewer->id, (int) $model->id)) {
            return false;
        }

        return $model->status->value === 'active';
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->role->rank() >= UserRole::Admin->rank();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role === UserRole::Superadmin;
    }

    public function viewAdminOverview(User $user): bool
    {
        return $user->isAdmin();
    }

    public function impersonate(User $viewer, User $model): bool
    {
        return $viewer->isAdmin() && $viewer->id !== $model->id;
    }

    public function impersonateStop(User $viewer): bool
    {
        return $viewer->isAdmin();
    }
}
