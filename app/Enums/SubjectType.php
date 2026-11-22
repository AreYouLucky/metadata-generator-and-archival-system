<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Kind of subject a record is about.
 */
enum SubjectType: string
{
    use HasOptions;

    case Person = 'person';
    case Organisation = 'organisation';
    case Place = 'place';
    case Topic = 'topic';
    case Event = 'event';
}
