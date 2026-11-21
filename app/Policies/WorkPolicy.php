<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Work;

/**
 * Works group the reels or parts of one production. Encoders manage their
 * own works; admins manage every work.
 */
class WorkPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Encoder);
    }

    public function view(User $user, Work $work): bool
    {
        return $this->create($user) && ($user->isAdmin() || $work->created_by === $user->id);
    }

    public function update(User $user, Work $work): bool
    {
        return $this->view($user, $work);
    }
}
