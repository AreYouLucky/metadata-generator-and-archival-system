<?php

namespace App\Enums;

/**
 * Kinds of access recorded in the access log.
 */
enum AccessAction: string
{
    case View = 'view';
    case Stream = 'stream';
    case Download = 'download';
    case Export = 'export';
}
