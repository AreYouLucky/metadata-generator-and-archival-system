<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Whether permission to use the record has been granted.
 */
enum PermissionStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
