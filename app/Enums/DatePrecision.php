<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How precise an archival date is.
 */
enum DatePrecision: string
{
    use HasOptions;

    case Day = 'day';
    case Month = 'month';
    case Year = 'year';
    case Circa = 'circa';
}
