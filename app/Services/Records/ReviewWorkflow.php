<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\ReviewAction;
use App\Enums\ReviewStatus;
use App\Models\Record;
use App\Models\User;
use App\Services\Catalogue\CatalogueCache;
use Illuminate\Support\Facades\DB;

/**
 * The approval workflow on `records.review_status` (PROJECT_SPEC §1):
 * draft → submitted → approved | changes_requested | rejected, and reopen
 * back to draft. Each step locks the row, so a double click or two admins at
 * once can't apply two decisions, and is logged with its notes in
 * `video_audit_logs`. Approving publishes (`published_at`); every other step
 * unpublishes, and any change to what is public clears the catalogue cache.
 */
class ReviewWorkflow
{
    public function __construct(
        private AuditTrail $audit,
        private CatalogueCache $catalogue,
    ) {}

    /**
     * @return bool false when the record had already left an editable state
     */
    public function submit(Record $record, User $user, ?string $notes): bool
    {
        return $this->transition(
            $record,
            fn (ReviewStatus $status): bool => $status->isEditableByEncoder(),
            function (Record $locked) use ($user, $notes): void {
                $locked->review_status = ReviewStatus::Submitted;
                $locked->submitted_at = now();
                $locked->save();

                $this->audit->log($locked, AuditAction::Submitted, $user, $notes);
            },
        );
    }

    /**
     * @return bool false when the record was no longer waiting for review
     */
    public function review(Record $record, User $admin, ReviewAction $action, ?string $notes): bool
    {
        return $this->transition(
            $record,
            fn (ReviewStatus $status): bool => $status === ReviewStatus::Submitted,
            function (Record $locked) use ($admin, $action, $notes): void {
                $locked->review_status = $action->status();
                $locked->reviewed_by = $admin->id;
                $locked->reviewed_at = now();
                $locked->published_at = $action === ReviewAction::Approve ? now() : null;
                $locked->save();

                $this->audit->log($locked, $action->auditAction(), $admin, $notes);
            },
        );
    }

    /**
     * @return bool false when the record was neither approved nor rejected
     */
    public function reopen(Record $record, User $admin, ?string $notes): bool
    {
        return $this->transition(
            $record,
            fn (ReviewStatus $status): bool => in_array($status, [ReviewStatus::Approved, ReviewStatus::Rejected], true),
            function (Record $locked) use ($admin, $notes): void {
                $locked->review_status = ReviewStatus::Draft;
                $locked->published_at = null;
                $locked->save();

                $this->audit->log($locked, AuditAction::Reopened, $admin, $notes);
            },
        );
    }

    /**
     * @param  callable(ReviewStatus): bool  $allowedFrom
     * @param  callable(Record): void  $apply
     */
    private function transition(Record $record, callable $allowedFrom, callable $apply): bool
    {
        $applied = DB::transaction(function () use ($record, $allowedFrom, $apply): bool {
            $locked = Record::query()->whereKey($record->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $allowedFrom($locked->review_status)) {
                return false;
            }

            $apply($locked);
            $record->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($applied) {
            $this->catalogue->flush();
        }

        return $applied;
    }
}
