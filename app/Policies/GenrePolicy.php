<?php

namespace App\Policies;

use App\Models\Genre;
use App\Models\User;

/**
 * An admin-managed lookup list: encoders pick from it in the workspace (ArchivePresenter options), admins manage it
 * (PROJECT_SPEC §1, admin duties).
 */
class GenrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, Genre $item): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function delete(User $user, Genre $item): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
