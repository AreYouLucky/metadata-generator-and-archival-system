<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Security classification of a record. Only unclassified records can be public.
 */
enum SecurityClassification: string
{
    use HasOptions;

    case Unclassified = 'unclassified';
    case Restricted = 'restricted';
    case Confidential = 'confidential';
    case Secret = 'secret';
    case TopSecret = 'top_secret';
}
