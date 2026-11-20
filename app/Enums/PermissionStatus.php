<?php

namespace App\Enums;

/**
 * Whether permission to use the record has been granted.
 */
enum PermissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
