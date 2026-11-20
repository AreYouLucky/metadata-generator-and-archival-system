<?php

namespace App\Enums;

/**
 * What started a fixity check.
 */
enum FixityTrigger: string
{
    case Ingest = 'ingest';
    case Schedule = 'schedule';
    case User = 'user';
}
