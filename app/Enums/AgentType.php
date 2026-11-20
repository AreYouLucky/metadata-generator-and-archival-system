<?php

namespace App\Enums;

/**
 * Who or what performed a preservation event.
 */
enum AgentType: string
{
    case User = 'user';
    case System = 'system';
    case Software = 'software';
}
