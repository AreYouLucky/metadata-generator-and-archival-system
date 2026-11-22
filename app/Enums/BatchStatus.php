<?php

namespace App\Enums;

/**
 * A batch is processing until every record in it has finished, failed or
 * been cancelled; then it is done (the failed count says how many didn't).
 */
enum BatchStatus: string
{
    case Processing = 'processing';
    case Done = 'done';
}
