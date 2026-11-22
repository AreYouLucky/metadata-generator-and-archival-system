<?php

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Record;
use App\Models\User;
use App\Services\Catalogue\Distribution;

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

    /**
     * Stopping the pipeline: whoever may edit the record (and so re-run it).
     */
    public function cancel(User $user, Record $record): bool
    {
        return $this->update($user, $record);
    }

    /**
     * A soft delete (kept for the audit log): the record's encoder while it is
     * a draft or has changes requested, admins always. Submitted and approved
     * records go through review first, so an encoder can't pull them.
     */
    public function delete(User $user, Record $record): bool
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
        return app(Distribution::class)->blockers($record) === [];
    }

    /**
     * Publish to YouTube (Phase 7): whoever may open the record (its encoder or
     * an admin), and only while the record may leave the archive.
     */
    public function publish(User $user, Record $record): bool
    {
        return $this->view($user, $record) && $this->distribute($user, $record);
    }

    /**
     * Submit for approval: the record's encoder (or an admin), only from
     * `draft` or `changes_requested`.
     */
    public function submit(User $user, Record $record): bool
    {
        return $this->view($user, $record) && $record->review_status->isEditableByEncoder();
    }

    /**
     * Approve, request changes or reject: admins, only while submitted.
     */
    public function review(User $user, Record $record): bool
    {
        return $user->is_active && $user->isAdmin() && $record->review_status === ReviewStatus::Submitted;
    }

    /**
     * Back to `draft` (and off the public site): admins, from approved or
     * rejected, so a rejected record can be worked on again.
     */
    public function reopen(User $user, Record $record): bool
    {
        return $user->is_active
            && $user->isAdmin()
            && in_array($record->review_status, [ReviewStatus::Approved, ReviewStatus::Rejected], true);
    }

    /**
     * Who viewed or streamed the record (IP addresses): admins only.
     */
    public function viewAccessLog(User $user, Record $record): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
