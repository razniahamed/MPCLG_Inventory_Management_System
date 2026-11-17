<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum AuthEventType: string
{
    use EnumValues;

    case LoginSuccess = 'login_success';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case Locked = 'locked';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case TokenRevoked = 'token_revoked';
    case MfaFailed = 'mfa_failed';
}
