<?php

namespace App\Enums;

/**
 * Privacy setting for a YouTube upload.
 */
enum YoutubePrivacy: string
{
    case Private = 'private';
    case Unlisted = 'unlisted';
    case Public = 'public';
}
