<?php

namespace App\Enums;

/**
 * How precise an archival date is.
 */
enum DatePrecision: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';
    case Circa = 'circa';
}
