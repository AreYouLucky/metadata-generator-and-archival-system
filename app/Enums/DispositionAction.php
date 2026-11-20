<?php

namespace App\Enums;

/**
 * What happens to a record when its retention period ends.
 */
enum DispositionAction: string
{
    case Retain = 'retain';
    case Transfer = 'transfer';
    case Destroy = 'destroy';
    case Review = 'review';
}
