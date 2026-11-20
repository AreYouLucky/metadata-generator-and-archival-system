<?php

namespace App\Enums;

/**
 * Platform presets for generated metadata.
 */
enum Preset: string
{
    case Youtube = 'youtube';
    case Tiktok = 'tiktok';
    case Instagram = 'instagram';
    case Linkedin = 'linkedin';
    case Generic = 'generic';
}
