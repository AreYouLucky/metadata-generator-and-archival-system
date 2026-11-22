<?php

namespace App\Services\Catalogue;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache for the public catalogue. Every key carries a version number, so
 * `flush()` (one increment) retires all cached pages at once on any store,
 * without cache tags. Entries also expire on their own after a few minutes,
 * as a safety net for changes that don't flush (e.g. an embargo ending).
 */
class CatalogueCache
{
    private const string VERSION_KEY = 'catalogue:version';

    private const int TTL_SECONDS = 600;

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember('catalogue:v'.$this->version().':'.$key, self::TTL_SECONDS, $callback);
    }

    /**
     * Called when a record is approved, reopened or edited while public, and
     * when a project, series or lookup list changes.
     */
    public function flush(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }
}
