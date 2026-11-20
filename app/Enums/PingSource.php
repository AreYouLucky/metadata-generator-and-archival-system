<?php

namespace App\Enums;

/**
 * Where a System status test ping came from: straight from Laravel, or as a
 * signed callback from the AI service's worker.
 */
enum PingSource: string
{
    case Laravel = 'laravel';
    case AiWorker = 'ai-worker';
}
