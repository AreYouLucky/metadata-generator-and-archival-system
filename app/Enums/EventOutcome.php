<?php

namespace App\Enums;

/**
 * Outcome of a preservation event.
 */
enum EventOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Warning = 'warning';
}
