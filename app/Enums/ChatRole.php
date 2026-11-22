<?php

namespace App\Enums;

/**
 * Who wrote a chat message: the person asking, or the assistant answering.
 */
enum ChatRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
