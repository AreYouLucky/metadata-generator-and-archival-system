<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

/**
 * Projects (events) and their series are shared catalogue entries: every
 * encoder files videos under the same events, so any active encoder or admin
 * can see them and add to them. Only the creator or an admin edits or deletes.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Encoder);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Edit the project and its series: the encoder who created it, or an admin.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->viewAny($user) && ($user->isAdmin() || $project->created_by === $user->id);
    }

    /**
     * Soft-delete the project. Its creator may do so only while nothing is
     * filed under it (other encoders' videos may be); admins may always.
     */
    public function delete(User $user, Project $project): bool
    {
        if (! $this->update($user, $project)) {
            return false;
        }

        return $user->isAdmin() || $project->records()->doesntExist();
    }

    /**
     * Add a series (a session, day or episode run) to the project.
     */
    public function addSeries(User $user, Project $project): bool
    {
        return $this->viewAny($user);
    }
}
