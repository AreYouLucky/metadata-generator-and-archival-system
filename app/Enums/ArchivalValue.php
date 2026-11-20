<?php

namespace App\Enums;

/**
 * Whether a record is kept permanently or disposed of after its retention period.
 */
enum ArchivalValue: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
}
