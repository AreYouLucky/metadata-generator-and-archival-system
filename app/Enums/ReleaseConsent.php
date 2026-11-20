<?php

namespace App\Enums;

/**
 * Whether signed release or consent forms are on file.
 */
enum ReleaseConsent: string
{
    case Available = 'available';
    case NotAvailable = 'not_available';
}
