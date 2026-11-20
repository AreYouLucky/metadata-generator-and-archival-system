<?php

namespace App\Enums;

/**
 * Where a project's video came from.
 */
enum SourceType: string
{
    case Upload = 'upload';
    case Url = 'url';
}
