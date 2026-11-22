<?php

namespace App\Enums;

/**
 * What an admin decides about a submitted record.
 */
enum ReviewAction: string
{
    case Approve = 'approve';
    case RequestChanges = 'request_changes';
    case Reject = 'reject';

    public function status(): ReviewStatus
    {
        return match ($this) {
            self::Approve => ReviewStatus::Approved,
            self::RequestChanges => ReviewStatus::ChangesRequested,
            self::Reject => ReviewStatus::Rejected,
        };
    }

    public function auditAction(): AuditAction
    {
        return match ($this) {
            self::Approve => AuditAction::Approved,
            self::RequestChanges => AuditAction::ChangesRequested,
            self::Reject => AuditAction::Rejected,
        };
    }

    /**
     * Rejecting or sending back needs a note, so the encoder knows why.
     */
    public function needsNotes(): bool
    {
        return $this !== self::Approve;
    }
}
