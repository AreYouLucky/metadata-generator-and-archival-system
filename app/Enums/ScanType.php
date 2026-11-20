<?php

namespace App\Enums;

/**
 * Video scan type derived from the stream's field order.
 */
enum ScanType: string
{
    case Progressive = 'progressive';
    case InterlacedTff = 'interlaced_tff';
    case InterlacedBff = 'interlaced_bff';
    case Unknown = 'unknown';
}
