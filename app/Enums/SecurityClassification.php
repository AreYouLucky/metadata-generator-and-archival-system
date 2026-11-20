<?php

namespace App\Enums;

/**
 * Security classification of a record. Only unclassified records can be public.
 */
enum SecurityClassification: string
{
    case Unclassified = 'unclassified';
    case Restricted = 'restricted';
    case Confidential = 'confidential';
    case Secret = 'secret';
    case TopSecret = 'top_secret';
}
