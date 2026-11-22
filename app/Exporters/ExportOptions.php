<?php

namespace App\Exporters;

/**
 * Export choices.
 *
 * - `fields`: a custom schema's dot-paths (JSON only); null exports the whole document.
 * - `language`: which transcript the subtitle and text formats use; null is the original.
 * - `timestamps`: start times on each TXT line.
 * - `forDistribution`: the export leaves the archive (public API): release
 *   forms and contracts are left out of the attachment list, and only
 *   confirmed subjects and keywords are kept.
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
        public bool $forDistribution = false,
    ) {}
}
