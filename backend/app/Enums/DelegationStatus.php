<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum DelegationStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
