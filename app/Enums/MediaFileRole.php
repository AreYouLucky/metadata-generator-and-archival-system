<?php

namespace App\Enums;

/**
 * Role of a stored file version within a project.
 */
enum MediaFileRole: string
{
    case Master = 'master';
    case Mezzanine = 'mezzanine';
    case Proxy = 'proxy';
    case Access = 'access';
}
