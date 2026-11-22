<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Whether a record is kept permanently or disposed of after its retention period.
 */
enum ArchivalValue: string
{
    use HasOptions;

    case Permanent = 'permanent';
    case Temporary = 'temporary';
}
