<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Makes a model's rows immutable once written: updates and deletes through
 * Eloquent throw. Used for audit logs (preservation events, fixity checks,
 * reviews, usage). Mass query-builder updates bypass model events, so never
 * run them against these tables.
 */
trait AppendOnly
{
    /**
     * Register the guards that block updates and deletes.
     */
    public static function bootAppendOnly(): void
    {
        static::updating(function (): never {
            throw new LogicException(static::class.' records are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException(static::class.' records are append-only and cannot be deleted.');
        });
    }
}
