<?php

namespace App\Enums;

/**
 * Kinds of events recorded in the append-only preservation log.
 */
enum PreservationEventType: string
{
    case Ingest = 'ingest';
    case Checksum = 'checksum';
    case FixityCheck = 'fixity_check';
    case Transcode = 'transcode';
    case Migration = 'migration';
    case Replacement = 'replacement';
    case MetadataChange = 'metadata_change';
    case Deletion = 'deletion';
}
