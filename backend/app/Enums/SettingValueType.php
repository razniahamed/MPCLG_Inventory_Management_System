<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum SettingValueType: string
{
    use EnumValues;

    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Json = 'json';

    public function cast(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($this) {
            self::String => $raw,
            self::Integer => (int) $raw,
            self::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::Json => json_decode($raw, true),
        };
    }

    public function serialize(mixed $value): string
    {
        return match ($this) {
            self::String => (string) $value,
            self::Integer => (string) (int) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            self::Json => json_encode($value),
        };
    }
}
