<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

/** pending → active ⇄ suspended → deactivated (Section 6) */
enum UserStatus: string
{
    use EnumValues;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    /** May this status sign in at all? Pending users sign in to set their password. */
    public function canSignIn(): bool
    {
        return $this === self::Active || $this === self::Pending;
    }
}
