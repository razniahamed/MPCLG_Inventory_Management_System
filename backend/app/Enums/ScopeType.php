<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum ScopeType: string
{
    use EnumValues;

    case Global = 'global';
    case Division = 'division';
    case Department = 'department';
    case Store = 'store';

    /** Table that scope_id points to, or null for global. */
    public function table(): ?string
    {
        return match ($this) {
            self::Global => null,
            self::Division => 'divisions',
            self::Department => 'departments',
            self::Store => 'stores',
        };
    }
}
