<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoType;

/**
 * An admin-managed lookup list: encoders pick from it in the workspace (ArchivePresenter options), admins manage it
 * (PROJECT_SPEC §1, admin duties).
 */
class VideoTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, VideoType $item): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function delete(User $user, VideoType $item): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
