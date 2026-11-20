<?php

namespace App\Enums;

/**
 * Who may access a record.
 */
enum AccessLevel: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Restricted = 'restricted';
}
