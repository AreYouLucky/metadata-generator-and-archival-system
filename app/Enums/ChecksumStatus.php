<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Result of comparing a file's checksums.
 */
enum ChecksumStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Ok = 'ok';
    case Mismatch = 'mismatch';
}
