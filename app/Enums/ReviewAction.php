<?php

namespace App\Enums;

/**
 * Actions recorded in a project's review history.
 */
enum ReviewAction: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
    case Reopened = 'reopened';
}
