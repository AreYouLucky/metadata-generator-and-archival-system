<?php

namespace App\Enums;

/**
 * State of one processing step.
 */
enum StageStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
    // Stopped by an encoder before it finished (POST /records/{record}/cancel).
    case Cancelled = 'cancelled';
}
