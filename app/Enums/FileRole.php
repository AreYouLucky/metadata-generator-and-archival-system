<?php

namespace App\Enums;

/**
 * Role of a stored file within a record.
 */
enum FileRole: string
{
    case Master = 'master';
    case Mezzanine = 'mezzanine';
    case Proxy = 'proxy';
    case Access = 'access';
}
