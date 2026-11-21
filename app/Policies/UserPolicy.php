<?php

namespace App\Policies;

use App\Models\User;

/**
 * Account management is admin-only. The rule that an admin can't demote or
 * deactivate themselves lives in Admin\UpdateUserRequest.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
