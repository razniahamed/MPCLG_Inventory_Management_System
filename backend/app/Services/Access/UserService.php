<?php

namespace App\Services\Access;

use App\Enums\AuditAction;
use App\Enums\AuthEventType;
use App\Enums\DelegationStatus;
use App\Enums\PermissionEffect;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Delegation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use App\Services\Audit\AuditLogger;
use App\Services\System\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * User lifecycle (Section 6): create, update, suspend, reactivate, deactivate,
 * reset password, revoke tokens, role assignment and per-user overrides.
 * Accounts are never deleted.
 */
class UserService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccessCache $cache,
        private readonly AdminGuard $adminGuard,
        private readonly ScopeValidator $scopes,
        private readonly AuthService $auth,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array{user: User, temporary_password: ?string}
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $temporary = empty($data['password']) ? $this->temporaryPassword() : null;

            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'officer_id' => $data['officer_id'] ?? null,
                'password' => $data['password'] ?? $temporary,
                'status' => UserStatus::Pending,
                'must_change_password' => true,
            ]);

            $this->audit->log(AuditAction::Created, $user, null, $user->only(['username', 'email', 'officer_id', 'status']), $data['reason'] ?? 'User created.');

            if (! empty($data['roles'])) {
                $this->assignRoles($user, $data['roles'], 'merge', $data['reason'] ?? 'Initial roles.');
            }

            return ['user' => $user->fresh(), 'temporary_password' => $temporary];
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $original = $user->getOriginal();
            $user->fill(array_intersect_key($data, array_flip(['username', 'email', 'officer_id'])))->save();
            $this->audit->logChanges($user, $original, $data['reason'] ?? null);

            return $user;
        });
    }

    public function suspend(User $user, string $reason): User
    {
        $this->assertNotSelf($user, 'suspend');
        $this->assertStatus($user, [UserStatus::Active, UserStatus::Pending], 'suspend');

        return DB::transaction(fn () => $this->adminGuard->guard(Auth::user(), $user, function () use ($user, $reason) {
            $this->changeStatus($user, UserStatus::Suspended, $reason);
            $this->revokeAllTokens($user);

            return $user;
        }));
    }

    public function reactivate(User $user, string $reason): User
    {
        $this->assertStatus($user, [UserStatus::Suspended], 'reactivate');

        return DB::transaction(function () use ($user, $reason) {
            $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();
            $target = $user->must_change_password && $user->password_changed_at === null ? UserStatus::Pending : UserStatus::Active;
            $this->changeStatus($user, $target, $reason);

            return $user;
        });
    }

    /**
     * Deactivate: keep all history, revoke tokens, end every role assignment,
     * override and delegation. Never deletes the account.
     */
    public function deactivate(User $user, string $reason): User
    {
        $this->assertNotSelf($user, 'deactivate');
        if ($user->status === UserStatus::Deactivated) {
            throw ApiException::businessRule('The account is already deactivated.');
        }

        return DB::transaction(fn () => $this->adminGuard->guard(Auth::user(), $user, function () use ($user, $reason) {
            $now = now();
            $actorId = Auth::id();

            $user->forceFill([
                'deactivated_at' => $now,
                'deactivated_by' => $actorId,
                'deactivation_reason' => $reason,
            ])->save();

            UserRole::query()->open()->where('user_id', $user->id)->update([
                'is_active' => false, 'valid_to' => $now, 'revoked_by' => $actorId,
                'revoked_at' => $now, 'revoke_reason' => 'Account deactivated: '.$reason,
            ]);

            UserPermission::query()->open()->where('user_id', $user->id)->update([
                'revoked_by' => $actorId, 'revoked_at' => $now,
            ]);

            // Delegates acting for this user must be refreshed before the delegation is closed.
            $this->cache->invalidateUser($user);

            Delegation::query()
                ->where('status', DelegationStatus::Active->value)
                ->where(fn ($q) => $q->where('delegator_user_id', $user->id)->orWhere('delegate_user_id', $user->id))
                ->update(['status' => DelegationStatus::Revoked->value, 'revoked_by' => $actorId, 'revoked_at' => $now]);

            $this->changeStatus($user, UserStatus::Deactivated, $reason);
            $this->revokeAllTokens($user);

            return $user;
        }));
    }

    /** @return string the new temporary password */
    public function resetPassword(User $user, string $reason): string
    {
        if ($user->status === UserStatus::Deactivated) {
            throw ApiException::businessRule('A deactivated account cannot have its password reset.');
        }

        return DB::transaction(function () use ($user, $reason) {
            $temporary = $this->temporaryPassword();

            $user->forceFill([
                'password' => $temporary,
                'must_change_password' => true,
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();

            $this->revokeAllTokens($user);
            $this->auth->event($user, AuthEventType::PasswordReset, request()?->ip(), request()?->userAgent());
            $this->audit->log(AuditAction::Updated, $user, null, ['password' => 'reset', 'must_change_password' => true], $reason);

            return $temporary;
        });
    }

    public function revokeTokens(User $user, string $reason): int
    {
        return DB::transaction(function () use ($user, $reason) {
            $count = $this->revokeAllTokens($user);
            $this->audit->log(AuditAction::Updated, $user, null, ['tokens_revoked' => $count], $reason);

            return $count;
        });
    }

    /**
     * Replace or merge role assignments.
     *
     * @param  array<int, array{role: string, scope_type?: string, scope_id?: int, valid_from?: string, valid_to?: ?string}>  $roles
     */
    public function assignRoles(User $user, array $roles, string $mode, string $reason): User
    {
        if ($user->status === UserStatus::Deactivated) {
            throw ApiException::businessRule('Roles cannot be assigned to a deactivated account.');
        }

        $run = function () use ($user, $roles, $mode, $reason) {
            $now = now();
            $actorId = Auth::id();
            $before = $this->describeAssignments($user);
            $keep = [];

            foreach ($roles as $index => $entry) {
                $role = Role::where('code', $entry['role'])->where('is_active', true)->first();
                if (! $role) {
                    throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                        "roles.{$index}.role" => ["Unknown or inactive role [{$entry['role']}]."],
                    ]);
                }

                $scope = $this->scopes->resolve($entry['scope_type'] ?? null, $entry['scope_id'] ?? null, "roles.{$index}");
                $validFrom = isset($entry['valid_from']) ? Carbon::parse($entry['valid_from']) : $now;
                $validTo = isset($entry['valid_to']) ? Carbon::parse($entry['valid_to']) : null;

                if ($validTo && $validTo->lte($validFrom)) {
                    throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                        "roles.{$index}.valid_to" => ['valid_to must be after valid_from.'],
                    ]);
                }

                $existing = UserRole::query()->open()
                    ->where('user_id', $user->id)
                    ->where('role_id', $role->id)
                    ->where('scope_type', $scope->type->value)
                    ->where(fn ($q) => $scope->id === null ? $q->whereNull('scope_id') : $q->where('scope_id', $scope->id))
                    ->first();

                if ($existing) {
                    if (array_key_exists('valid_to', $entry)) {
                        $existing->update(['valid_to' => $validTo]);
                    }
                    $keep[] = $existing->id;

                    continue;
                }

                $assignment = UserRole::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'scope_type' => $scope->type,
                    'scope_id' => $scope->id,
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'granted_by' => $actorId,
                    'granted_at' => $now,
                ]);
                $keep[] = $assignment->id;
            }

            if ($mode === 'replace') {
                UserRole::query()->open()
                    ->where('user_id', $user->id)
                    ->whereNotIn('id', $keep)
                    ->update([
                        'is_active' => false, 'valid_to' => $now, 'revoked_by' => $actorId,
                        'revoked_at' => $now, 'revoke_reason' => $reason,
                    ]);
            }

            $this->cache->invalidateUser($user);
            $this->audit->log(AuditAction::PermissionChanged, $user, ['roles' => $before], ['roles' => $this->describeAssignments($user), 'mode' => $mode], $reason);

            return $user->fresh();
        };

        return DB::transaction(fn () => $this->adminGuard->guard(Auth::user(), $user, $run));
    }

    /**
     * Turn one permission ON (allow), OFF (deny) or back to role default (inherit) for one user.
     */
    public function setPermissionOverride(User $user, Permission $permission, string $effect, array $data): ?UserPermission
    {
        if ($user->status === UserStatus::Deactivated) {
            throw ApiException::businessRule('Permissions cannot be changed on a deactivated account.');
        }

        $hasScope = isset($data['scope']) && is_array($data['scope']);
        $scope = $hasScope
            ? $this->scopes->resolve($data['scope']['scope_type'] ?? null, $data['scope']['scope_id'] ?? null)
            : Scope::global();

        if (! $scope->isGlobal() && ! $permission->supports_scope) {
            throw ApiException::businessRule("The '{$permission->code}' permission cannot be limited to a scope.");
        }

        $reason = $data['reason'];

        $run = function () use ($user, $permission, $effect, $data, $scope, $hasScope, $reason) {
            $now = now();
            $actorId = Auth::id();

            $open = UserPermission::query()->open()
                ->where('user_id', $user->id)
                ->where('permission_id', $permission->id);

            // "inherit" without a scope clears every override for this permission.
            if ($effect !== 'inherit' || $hasScope) {
                $open->where('scope_type', $scope->type->value)
                    ->where(fn ($q) => $scope->id === null ? $q->whereNull('scope_id') : $q->where('scope_id', $scope->id));
            }

            $previous = $open->get();
            $open->update(['revoked_by' => $actorId, 'revoked_at' => $now]);

            $override = null;
            if ($effect !== 'inherit') {
                $validFrom = isset($data['valid_from']) ? Carbon::parse($data['valid_from']) : $now;
                $validTo = isset($data['valid_to']) ? Carbon::parse($data['valid_to']) : null;

                $override = UserPermission::create([
                    'user_id' => $user->id,
                    'permission_id' => $permission->id,
                    'effect' => PermissionEffect::from($effect),
                    'scope_type' => $scope->type,
                    'scope_id' => $scope->id,
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'reason' => $reason,
                    'granted_by' => $actorId,
                    'granted_at' => $now,
                ]);
            }

            $this->cache->invalidateUser($user);
            $this->audit->log(
                AuditAction::PermissionChanged,
                $user,
                ['permission' => $permission->code, 'overrides' => $previous->map(fn ($o) => $o->effect->value.'@'.$o->scope_type->value.($o->scope_id ? ':'.$o->scope_id : ''))->all()],
                ['permission' => $permission->code, 'effect' => $effect] + $scope->toArray() + ['valid_to' => $data['valid_to'] ?? null],
                $reason,
            );

            return $override;
        };

        return DB::transaction(fn () => $this->adminGuard->guard(Auth::user(), $user, $run));
    }

    private function changeStatus(User $user, UserStatus $status, string $reason): void
    {
        $old = $user->status;
        $user->forceFill(['status' => $status])->save();
        $this->cache->invalidateUser($user);
        $this->audit->log(AuditAction::StatusChanged, $user, ['status' => $old->value], ['status' => $status->value], $reason);
    }

    private function revokeAllTokens(User $user): int
    {
        $count = $user->tokens()->delete();
        $this->auth->event($user, AuthEventType::TokenRevoked, request()?->ip(), request()?->userAgent());

        return $count;
    }

    private function assertNotSelf(User $user, string $action): void
    {
        if (Auth::id() === $user->id) {
            throw ApiException::businessRule("You cannot {$action} your own account.");
        }
    }

    /** @param  array<int, UserStatus>  $allowed */
    private function assertStatus(User $user, array $allowed, string $action): void
    {
        if (! in_array($user->status, $allowed, true)) {
            throw ApiException::businessRule("Cannot {$action} an account whose status is '{$user->status->value}'.");
        }
    }

    private function describeAssignments(User $user): array
    {
        return UserRole::query()->open()
            ->where('user_id', $user->id)
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->get(['roles.code', 'user_roles.scope_type', 'user_roles.scope_id', 'user_roles.valid_to'])
            ->map(fn ($r) => $r->code.'@'.$r->scope_type->value.($r->scope_id ? ':'.$r->scope_id : ''))
            ->all();
    }

    private function temporaryPassword(): string
    {
        $length = max(12, $this->settings->int('auth.temp_password_length'));

        // Guarantee every character class required by the password policy.
        $password = Str::password($length - 4)
            .chr(random_int(65, 90)).chr(random_int(97, 122)).random_int(0, 9).'#';

        return str_shuffle(preg_replace('/[^A-Za-z0-9#@!%*?&]/', 'x', $password));
    }
}
