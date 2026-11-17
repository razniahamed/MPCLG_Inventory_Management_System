<?php

namespace App\Services\Access;

use App\Models\Delegation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Cache invalidation for resolved permissions.
 *
 * Per-user grants are cached under users.permission_version, so bumping it
 * invalidates instantly. Module / permission on-off switches affect everyone,
 * so they bump a global version instead.
 */
class AccessCache
{
    private const GLOBAL_KEY = 'access.global_version';

    public function globalVersion(): int
    {
        return (int) Cache::get(self::GLOBAL_KEY, 1);
    }

    public function bumpGlobal(): void
    {
        Cache::forever(self::GLOBAL_KEY, $this->globalVersion() + 1);
    }

    /**
     * Bump a user's version, and the versions of anyone currently acting
     * under a delegation from them (their delegated grants derive from this user).
     */
    public function invalidateUser(User $user): void
    {
        $user->bumpPermissionVersion();

        $delegateIds = Delegation::query()
            ->where('delegator_user_id', $user->id)
            ->where('status', 'active')
            ->where('valid_to', '>', now())
            ->pluck('delegate_user_id')
            ->unique();

        if ($delegateIds->isNotEmpty()) {
            User::whereIn('id', $delegateIds)->increment('permission_version');
        }
    }

    /** @param  iterable<int>  $userIds */
    public function invalidateUsers(iterable $userIds): void
    {
        foreach (User::whereIn('id', collect($userIds)->unique())->get() as $user) {
            $this->invalidateUser($user);
        }
    }
}
