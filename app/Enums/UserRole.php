<?php

namespace App\Enums;

/**
 * Roles a user can hold. Admins pass every role check.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Encoder = 'encoder';
    case User = 'user';
}
