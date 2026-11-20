<?php

namespace App\Enums;

/**
 * Approval state of a record.
 */
enum ReviewStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';

    /**
     * Whether the owning encoder may still edit (and submit) the record.
     */
    public function isEditableByEncoder(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }
}
