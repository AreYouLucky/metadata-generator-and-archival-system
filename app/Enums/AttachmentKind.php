<?php

namespace App\Enums;

/**
 * Kind of private file attached to a record.
 */
enum AttachmentKind: string
{
    case ReleaseForm = 'release_form';
    case Contract = 'contract';
    case Sidecar = 'sidecar';
    case Subtitle = 'subtitle';
    case Document = 'document';
    case Other = 'other';
}
