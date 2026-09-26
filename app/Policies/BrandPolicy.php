<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\User;

/**
 * Whitelabel brand access: admins manage all brands, clients only their own.
 */
class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->role === UserRole::Superadmin
            || $this->isClient($user);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->isAdmin() || $user->role === UserRole::Superadmin
            || ($this->isClient($user) && (int) $user->brand_id === (int) $brand->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->role === UserRole::Superadmin;
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->isAdmin() || $user->role === UserRole::Superadmin
            || ($this->isClient($user) && (int) $user->brand_id === (int) $brand->id);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->isAdmin() || $user->role === UserRole::Superadmin;
    }

    protected function isClient(User $user): bool
    {
        return $user->role === UserRole::Client && $user->brand_id !== null;
    }
}
