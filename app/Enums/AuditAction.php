<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Actions recorded in a record's audit log, including approval steps and preservation events.
 */
enum AuditAction: string
{
    use HasOptions;

    case Uploaded = 'uploaded';
    case Updated = 'updated';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Reopened = 'reopened';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Transcoded = 'transcoded';
    case Migrated = 'migrated';
    case Replaced = 'replaced';
    case ChecksumVerified = 'checksum_verified';
    case ChecksumFailed = 'checksum_failed';
    case Exported = 'exported';
}
