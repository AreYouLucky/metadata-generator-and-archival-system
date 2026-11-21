<?php

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Enums\SecurityClassification;
use App\Enums\UserRole;
use App\Models\Record;
use App\Models\User;

/**
 * Encoders see and edit only the records they created (and edit only while the
 * record is a draft or has changes requested); admins see and edit everything.
 * `distribute` decides whether a record may leave the archive.
 */
class RecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Encoder);
    }

    public function view(User $user, Record $record): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->isAdmin() || ($user->role === UserRole::Encoder && $record->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole(UserRole::Encoder);
    }

    public function update(User $user, Record $record): bool
    {
        if (! $this->view($user, $record)) {
            return false;
        }

        return $user->isAdmin() || $record->review_status->isEditableByEncoder();
    }

    /**
     * Metadata regeneration costs AI tokens, so it needs edit rights, not just view.
     */
    public function generate(User $user, Record $record): bool
    {
        return $this->update($user, $record);
    }

    /**
     * Re-running the pipeline costs transcription and AI tokens, like a regeneration.
     */
    public function retry(User $user, Record $record): bool
    {
        return $this->update($user, $record);
    }

    public function export(User $user, Record $record): bool
    {
        return $this->view($user, $record);
    }

    /**
     * Whether the record may leave the archive (PROJECT_SPEC §8, distribution
     * policy): the public catalogue, YouTube publishing, public-API export and
     * batch ZIPs all ask this, for guests too. It needs an approved and
     * published record that isn't deleted, an `unclassified` classification,
     * and rights that allow public access (public, permission approved, no
     * embargo in force). The viewer doesn't matter, so `$user` may be null.
     */
    public function distribute(?User $user, Record $record): bool
    {
        $record->loadMissing('rights');

        return $record->review_status === ReviewStatus::Approved
            && $record->published_at !== null
            && ! $record->trashed()
            && $record->security_classification === SecurityClassification::Unclassified
            && ($record->rights?->allowsPublicAccess() ?? false);
    }
}
