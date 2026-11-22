<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ExportSchema;
use App\Models\User;

/**
 * Export schemas are personal: each encoder or admin sees and changes only
 * their own (admins included, since a schema is a preference, not a record).
 */
class ExportSchemaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Encoder);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function use(User $user, ExportSchema $schema): bool
    {
        return $this->viewAny($user) && $schema->user_id === $user->id;
    }

    public function update(User $user, ExportSchema $schema): bool
    {
        return $this->use($user, $schema);
    }

    public function delete(User $user, ExportSchema $schema): bool
    {
        return $this->use($user, $schema);
    }
}
