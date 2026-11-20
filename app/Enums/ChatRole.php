<?php

namespace App\Enums;

/**
 * Author of a chat message.
 */
enum ChatRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
