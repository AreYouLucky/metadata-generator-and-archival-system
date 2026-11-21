<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where a descriptive value came from. Anything not manual is a suggestion until confirmed.
 */
enum MetadataSource: string
{
    use HasOptions;

    case Manual = 'manual';
    case Ai = 'ai';
    case Transcript = 'transcript';
    case Ocr = 'ocr';
    case ContainerTag = 'container_tag';
    case ContainerGps = 'container_gps';
}
