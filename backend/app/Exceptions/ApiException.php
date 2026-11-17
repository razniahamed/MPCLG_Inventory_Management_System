<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An error that maps directly to the API error envelope (Section 4).
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function businessRule(string $message, array $errors = []): self
    {
        return new self($message, 'BUSINESS_RULE_VIOLATION', 422, $errors);
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action.', string $code = 'FORBIDDEN'): self
    {
        return new self($message, $code, 403);
    }

    public static function permissionDenied(string $permission): self
    {
        return new self("You do not have the '{$permission}' permission.", 'PERMISSION_DENIED', 403);
    }

    public static function accountDisabled(): self
    {
        return new self('This account is disabled.', 'ACCOUNT_DISABLED', 403);
    }

    public static function accountLocked(?string $until = null): self
    {
        return new self(
            $until ? "Account locked until {$until} after too many failed logins." : 'Account locked after too many failed logins.',
            'ACCOUNT_LOCKED',
            423,
        );
    }

    public static function conflict(string $message, string $code = 'CONFLICT'): self
    {
        return new self($message, $code, 409);
    }

    public static function passwordChangeRequired(): self
    {
        return new self('You must change your password before continuing.', 'PASSWORD_CHANGE_REQUIRED', 403);
    }
}
