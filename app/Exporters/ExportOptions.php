<?php

namespace App\Exporters;

/**
 * Export choices.
 *
 * - `fields`: a custom schema's dot-paths (JSON only); null exports the whole document.
 * - `language`: which transcript the subtitle and text formats use; null is the original.
 * - `timestamps`: start times on each TXT line.
 */
final readonly class ExportOptions
{
    /**
     * @param  list<string>|null  $fields
     */
    public function __construct(
        public bool $pretty = true,
        public ?array $fields = null,
        public ?string $language = null,
        public bool $timestamps = false,
    ) {}
}
