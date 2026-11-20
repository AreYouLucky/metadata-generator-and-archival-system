<?php

namespace App\Enums;

/**
 * What the System status "send test ping" button exercises.
 */
enum PingTarget: string
{
    /** Laravel → queue → Reverb → browser. */
    case Broadcast = 'broadcast';

    /** Laravel → AI service → Celery → signed callback → Reverb → browser. */
    case AiService = 'ai_service';
}
