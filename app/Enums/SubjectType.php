<?php

namespace App\Enums;

/**
 * Kind of subject a record is about.
 */
enum SubjectType: string
{
    case Person = 'person';
    case Organisation = 'organisation';
    case Place = 'place';
    case Topic = 'topic';
    case Event = 'event';
}
