<?php

namespace App\Enums;

/**
 * Processing state of a record's pipeline.
 */
enum ProcessingStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
