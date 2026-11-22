<?php

namespace App\Services\Catalogue;

use App\Enums\AccessLevel;
use App\Enums\PermissionStatus;
use App\Enums\ReviewStatus;
use App\Enums\SecurityClassification;
use App\Models\Record;
use Illuminate\Database\Eloquent\Builder;

/**
 * The distribution rule (PROJECT_SPEC §8): a record may leave the archive only
 * when it is approved and published, not deleted, `unclassified`, and its
 * rights allow public access (public, permission approved, no embargo in
 * force). Rights are public by default (owner's choice), so a record whose
 * rights were never filled in counts as public. `RecordPolicy::distribute` asks `blockers()` for one record; the
 * public catalogue filters lists with `constrain()`. Both live here so they
 * cannot drift apart.
 */
class Distribution
{
    /**
     * Why the record may not be distributed; empty when it may. With
     * `$ignoreApproval`, the approval itself is left out, so a reviewer can see
     * whether approving would publish the record.
     *
     * @return list<string>
     */
    public function blockers(Record $record, bool $ignoreApproval = false): array
    {
        $record->loadMissing('rights');
        $rights = $record->rights;
        $blockers = [];

        if (! $ignoreApproval && ($record->review_status !== ReviewStatus::Approved || $record->published_at === null)) {
            $blockers[] = 'It is not approved.';
        }

        if ($record->trashed()) {
            $blockers[] = 'It is deleted.';
        }

        if ($record->security_classification !== SecurityClassification::Unclassified) {
            $blockers[] = 'Its security classification is '.strtolower($record->security_classification->label()).'.';
        }

        // Public by default (owner's choice): no rights row means public
        // access with permission approved and no embargo.
        if ($rights === null) {
            return $blockers;
        }

        if ($rights->access_level !== AccessLevel::Public) {
            $blockers[] = 'Its access level is '.$rights->access_level->value.', not public.';
        }

        if ($rights->permission_status !== PermissionStatus::Approved) {
            $blockers[] = 'Its permission status is '.$rights->permission_status->value.', not approved.';
        }

        if ($rights->isEmbargoed()) {
            $blockers[] = 'It is under embargo until '.$rights->embargo_until?->toDateString().'.';
        }

        return $blockers;
    }

    /**
     * Limits a records query to the ones that may be distributed. Soft-deleted
     * rows are already excluded by the model's global scope.
     *
     * @param  Builder<Record>  $query
     * @return Builder<Record>
     */
    public static function constrain(Builder $query): Builder
    {
        return $query
            ->where('records.review_status', ReviewStatus::Approved)
            ->whereNotNull('records.published_at')
            ->where('records.security_classification', SecurityClassification::Unclassified)
            // No rights row counts as public (the default); one that exists must allow it.
            ->whereDoesntHave('rights', fn (Builder $rights) => $rights->where(fn (Builder $blocking) => $blocking
                ->where('access_level', '!=', AccessLevel::Public)
                ->orWhere('permission_status', '!=', PermissionStatus::Approved)
                ->orWhere('embargo_until', '>', now())));
    }
}
