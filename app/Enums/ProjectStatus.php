<?php

namespace App\Enums;

/**
 * Processing state of a project's pipeline.
 */
enum ProjectStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
