<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Kind of private file attached to a record.
 */
enum AttachmentKind: string
{
    use HasOptions;

    case ReleaseForm = 'release_form';
    case Contract = 'contract';
    case Sidecar = 'sidecar';
    case Subtitle = 'subtitle';
    case Document = 'document';
    case Other = 'other';
}
