<?php

namespace App\Exporters;

/**
 * Export choices. Custom schemas (dot-path field lists) and translations arrive in Phase 2.
 */
final readonly class ExportOptions
{
    public function __construct(
        public bool $pretty = true,
    ) {}
}
