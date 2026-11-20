<?php

namespace App\Enums;

/**
 * Actions recorded in a record's audit log.
 */
enum AuditAction: string
{
    case Uploaded = 'uploaded';
    case Updated = 'updated';
    case Approved = 'approved';
    case Unapproved = 'unapproved';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ChecksumVerified = 'checksum_verified';
    case ChecksumFailed = 'checksum_failed';
    case Exported = 'exported';
}
