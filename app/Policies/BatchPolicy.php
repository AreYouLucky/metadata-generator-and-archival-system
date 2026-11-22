<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Batch;
use App\Models\User;

/**
 * Like records: encoders see only the batches they uploaded; admins see all.
 */
class BatchPolicy
{
    public function view(User $user, Batch $batch): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->isAdmin() || ($user->role === UserRole::Encoder && $batch->created_by === $user->id);
    }

    /**
     * Uploading a batch creates records, so it needs the right to create them.
     */
    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Encoder);
    }

    /**
     * The ZIP holds exports of records the viewer may already see; records
     * the archive restricts are left out of it (BatchArchive).
     */
    public function download(User $user, Batch $batch): bool
    {
        return $this->view($user, $batch);
    }
}
