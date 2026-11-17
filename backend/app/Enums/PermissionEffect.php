<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum PermissionEffect: string
{
    use EnumValues;

    case Allow = 'allow';
    case Deny = 'deny';
}
