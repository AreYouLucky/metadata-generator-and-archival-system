<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What happens to a record when its retention period ends.
 */
enum DispositionAction: string
{
    use HasOptions;

    case Retain = 'retain';
    case Transfer = 'transfer';
    case Destroy = 'destroy';
    case Review = 'review';
}
