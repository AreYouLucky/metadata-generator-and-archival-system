<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Role of a stored file within a record.
 */
enum FileRole: string
{
    use HasOptions;

    case Master = 'master';
    case Mezzanine = 'mezzanine';
    case Proxy = 'proxy';
    case Access = 'access';
}
