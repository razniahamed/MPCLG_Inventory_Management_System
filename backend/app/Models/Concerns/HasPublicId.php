<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Tables exposed through URLs carry a ULID public_id so internal ids are not guessable.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
