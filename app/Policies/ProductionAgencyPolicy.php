<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductionAgency;
use App\Models\User;

/**
 * Agencies and their production units: encoders pick from the list, admins
 * manage it (PROJECT_SPEC §1, admin duties).
 */
class ProductionAgencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Encoder);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, ProductionAgency $agency): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function delete(User $user, ProductionAgency $agency): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
