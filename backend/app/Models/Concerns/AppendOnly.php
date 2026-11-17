<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * [append] tables: rows may be inserted, never updated or deleted.
 * (Production also removes UPDATE/DELETE privileges from the DB user.)
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function ($model) {
            throw new LogicException(static::class.' is append-only and cannot be updated.');
        });

        static::deleting(function ($model) {
            throw new LogicException(static::class.' is append-only and cannot be deleted.');
        });
    }

    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }
}
