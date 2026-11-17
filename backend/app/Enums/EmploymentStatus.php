<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum EmploymentStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Transferred = 'transferred';
    case Retired = 'retired';
    case Resigned = 'resigned';
    case Suspended = 'suspended';

    public function hasLeft(): bool
    {
        return in_array($this, [self::Transferred, self::Retired, self::Resigned], true);
    }
}
