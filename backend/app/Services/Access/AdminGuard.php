<?php

namespace App\Services\Access;

use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\User;

/**
 * Lock-out protection (Section 5.4 rule 7): at least one active user must keep
 * role.manage + permission.override_user, and nobody may remove their own
 * last administrative permission.
 */
class AdminGuard
{
    public const ADMIN_PERMISSIONS = ['role.manage', 'permission.override_user'];

    public function __construct(private readonly PermissionResolver $resolver) {}

    public function isAdministrator(User $user): bool
    {
        $user = $user->fresh() ?? $user;

        foreach (self::ADMIN_PERMISSIONS as $code) {
            if (! $this->resolver->explain($user, $code, null, fresh: true)['allowed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run a change and roll it back if it would leave the system without an
     * administrator, or strip the acting user of their own admin rights.
     * Must be called inside a DB transaction.
     */
    public function guard(?User $actor, ?User $target, callable $change): mixed
    {
        $actorWasAdmin = $actor && $target && $actor->is($target) ? $this->isAdministrator($actor) : false;

        $result = $change();

        if ($actorWasAdmin && ! $this->isAdministrator($actor)) {
            throw ApiException::businessRule('You cannot remove your own last administrative permission.');
        }

        $this->assertAdministratorsRemain();

        return $result;
    }

    public function assertAdministratorsRemain(): void
    {
        $candidates = User::query()->where('status', UserStatus::Active->value)->get();

        foreach ($candidates as $candidate) {
            if ($this->isAdministrator($candidate)) {
                return;
            }
        }

        throw ApiException::businessRule('This change would leave no active administrator (role.manage + permission.override_user). Assign another administrator first.');
    }
}
