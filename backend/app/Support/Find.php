<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolve a record from either its public ULID or its numeric id.
 *
 * Never use where('public_id', $v)->orWhere('id', $v): MySQL casts a ULID such
 * as "01M7…" to the number 1 and silently matches the wrong record.
 */
final class Find
{
    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    public static function byKey(string $model, mixed $value): Model
    {
        $value = (string) $value;

        return ctype_digit($value)
            ? $model::query()->whereKey((int) $value)->firstOrFail()
            : $model::query()->where('public_id', $value)->firstOrFail();
    }
}
