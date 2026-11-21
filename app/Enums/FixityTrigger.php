<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What started a fixity check.
 */
enum FixityTrigger: string
{
    use HasOptions;

    case Ingest = 'ingest';
    case Schedule = 'schedule';
    case User = 'user';
}
