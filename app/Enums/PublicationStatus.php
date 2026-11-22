<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A YouTube publication: queued, uploading the file, waiting for YouTube to
 * process it, published (processed and live at its privacy), or failed.
 */
enum PublicationStatus: string
{
    use HasOptions;

    case Queued = 'queued';
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Published = 'published';
    case Failed = 'failed';

    /**
     * Still on its way to YouTube, so another publish of the record waits.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Uploading, self::Processing], true);
    }
}
