<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Holds the per-request correlation id (ULID) used in responses and audit rows.
 */
class RequestContext
{
    private static ?string $id = null;

    public static function id(): string
    {
        return self::$id ??= (string) Str::ulid();
    }

    public static function set(string $id): void
    {
        self::$id = $id;
    }

    public static function reset(): void
    {
        self::$id = null;
    }
}
