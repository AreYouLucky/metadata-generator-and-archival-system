<?php

namespace App\Enums;

/**
 * A question is `Pending` until it gets a reply, then `Answered`. A reply is
 * `Answered` (backed by the record's data), `NotFound` (the record's data
 * doesn't hold the answer, or the question isn't about the record) or
 * `Failed` (the AI service could not answer).
 */
enum ChatMessageStatus: string
{
    case Pending = 'pending';
    case Answered = 'answered';
    case NotFound = 'not_found';
    case Failed = 'failed';
}
