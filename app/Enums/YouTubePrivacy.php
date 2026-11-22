<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A YouTube video's `status.privacyStatus`.
 */
enum YouTubePrivacy: string
{
    use HasOptions;

    case Private = 'private';
    case Unlisted = 'unlisted';
    case Public = 'public';
}
