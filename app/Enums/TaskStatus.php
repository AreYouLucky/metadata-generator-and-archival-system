<?php

namespace App\Enums;

/**
 * State of a background task such as a clip, translation, batch or publication.
 */
enum TaskStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
}
