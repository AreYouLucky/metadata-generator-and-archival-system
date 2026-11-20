<?php

namespace App\Enums;

/**
 * Result of comparing a file's checksums.
 */
enum ChecksumStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Mismatch = 'mismatch';
}
