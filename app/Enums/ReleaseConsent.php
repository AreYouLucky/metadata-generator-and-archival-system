<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Whether signed release or consent forms are on file.
 */
enum ReleaseConsent: string
{
    use HasOptions;

    case Available = 'available';
    case NotAvailable = 'not_available';
}
