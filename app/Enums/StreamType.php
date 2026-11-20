<?php

namespace App\Enums;

/**
 * Type of a stream inside a media container.
 */
enum StreamType: string
{
    case Video = 'video';
    case Audio = 'audio';
    case Subtitle = 'subtitle';
    case Data = 'data';
}
