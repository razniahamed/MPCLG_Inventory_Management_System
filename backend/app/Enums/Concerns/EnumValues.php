<?php

namespace App\Enums\Concerns;

trait EnumValues
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * SQL list for CHECK constraints, e.g. 'a','b','c'.
     */
    public static function sqlList(): string
    {
        return implode(',', array_map(fn (string $v) => "'{$v}'", self::values()));
    }
}
