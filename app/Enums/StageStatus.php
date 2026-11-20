<?php

namespace App\Enums;

/**
 * State of a single pipeline stage.
 */
enum StageStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
