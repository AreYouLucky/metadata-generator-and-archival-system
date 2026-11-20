<?php

namespace App\Enums;

/**
 * Kind of subject a video is about.
 */
enum SubjectType: string
{
    case Person = 'person';
    case Organisation = 'organisation';
    case Place = 'place';
    case Topic = 'topic';
    case Event = 'event';
}
