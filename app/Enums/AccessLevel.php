<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Who may access a record.
 */
enum AccessLevel: string
{
    use HasOptions;

    case Public = 'public';
    case Internal = 'internal';
    case Restricted = 'restricted';
}
