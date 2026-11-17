<?php

namespace App\Services\Access;

use App\Enums\PermissionEffect;
use App\Enums\ScopeType;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Permission resolution algorithm (Section 5.2):
 *
 *  1. user not active / locked                         → DENY
 *  2. module disabled or permission disabled           → DENY
 *  3. unexpired, unrevoked DENY override matching scope → DENY (beats everything)
 *  4. unexpired, unrevoked ALLOW override matching scope → ALLOW
 *  5. current role assignment (matching scope) with the permission → ALLOW
 *     else current delegation giving it                → ALLOW
 *     else                                             → DENY
 *  6. business policy checks happen in Policies / services, not here.
 *
 * There is NO super-admin bypass.
 */
class PermissionResolver
{
    /** @var array<int, array<string, array<int, array>>> per-request memo of grants by user id + version */
    private array $memo = [];

    /** @var array<int, array{division_id: int, parent_department_id: ?int}>|null */
    private ?array $departmentMap = null;

    public function __construct(private readonly AccessCache $cache) {}

    /**
     * Can the user use this permission (in this scope)?
     * A null scope means "anywhere": row-level policies narrow it further.
     */
    public function can(User $user, string $code, ?Scope $scope = null): bool
    {
        return $this->explain($user, $code, $scope)['allowed'];
    }

    /**
     * Full decision with the reason and the grants that apply.
     *
     * @return array{allowed: bool, reason: string, sources: array<int, array>}
     */
    public function explain(User $user, string $code, ?Scope $scope = null, bool $fresh = false): array
    {
        if (! $user->isActive()) {
            return $this->decision(false, $user->isLocked() ? 'account_locked' : 'account_not_active');
        }

        $meta = $this->permissionMeta()[$code] ?? null;
        if ($meta === null) {
            return $this->decision(false, 'unknown_permission');
        }
        if (! $meta['module_enabled']) {
            return $this->decision(false, 'module_disabled');
        }
        if (! $meta['enabled']) {
            return $this->decision(false, 'permission_disabled');
        }

        $grants = array_values(array_filter(
            $this->grants($user, $fresh)[$code] ?? [],
            fn (array $g) => $this->isCurrent($g),
        ));

        $denies = array_values(array_filter($grants, fn ($g) => $g['effect'] === PermissionEffect::Deny->value));
        $allows = array_values(array_filter($grants, fn ($g) => $g['effect'] === PermissionEffect::Allow->value));

        if ($scope !== null) {
            $matchingDeny = array_values(array_filter($denies, fn ($g) => $this->covers($g, $scope)));
            if ($matchingDeny !== []) {
                return $this->decision(false, 'denied_by_override', $matchingDeny);
            }

            $matchingAllow = array_values(array_filter($allows, fn ($g) => $this->covers($g, $scope)));

            return $matchingAllow === []
                ? $this->decision(false, 'not_granted')
                : $this->decision(true, $this->reasonFor($matchingAllow), $matchingAllow);
        }

        // Unscoped: allowed if some allow grant is not fully cancelled by a deny.
        $effective = array_values(array_filter($allows, function (array $allow) use ($denies) {
            $grantScope = Scope::from($allow['scope_type'], $allow['scope_id']);
            foreach ($denies as $deny) {
                if ($this->covers($deny, $grantScope)) {
                    return false;
                }
            }

            return true;
        }));

        if ($effective === []) {
            return $denies !== []
                ? $this->decision(false, 'denied_by_override', $denies)
                : $this->decision(false, 'not_granted');
        }

        return $this->decision(true, $this->reasonFor($effective), $effective);
    }

    /**
     * Every permission the user can use right now, with the scopes it applies to.
     *
     * @return array<string, array<int, array{scope_type: string, scope_id: ?int, source: string}>>
     */
    public function effectivePermissions(User $user): array
    {
        $result = [];
        foreach (array_keys($this->permissionMeta()) as $code) {
            $decision = $this->explain($user, $code);
            if ($decision['allowed']) {
                $result[$code] = array_map(fn ($g) => [
                    'scope_type' => $g['scope_type'],
                    'scope_id' => $g['scope_id'],
                    'source' => $g['source'],
                ], $decision['sources']);
            }
        }

        return $result;
    }

    /** @return array<int, Scope> scopes in which the user holds the permission */
    public function scopesFor(User $user, string $code): array
    {
        $decision = $this->explain($user, $code);

        return array_map(fn ($g) => Scope::from($g['scope_type'], $g['scope_id']), $decision['sources']);
    }

    /**
     * Raw grants (allow + deny) for the user, keyed by permission code. Cached
     * by users.permission_version. Never cached while inside a DB transaction,
     * so rolled-back changes can't poison the cache.
     *
     * @return array<string, array<int, array>>
     */
    public function grants(User $user, bool $fresh = false): array
    {
        $inTransaction = $this->inTransaction();

        if ($fresh || $inTransaction) {
            return $this->buildGrants($user);
        }

        $version = (int) User::whereKey($user->id)->value('permission_version');
        $key = "access.grants.{$user->id}.v{$version}";

        return $this->memo[$user->id][$key] ??= Cache::remember(
            $key,
            config('mpclg.permission_cache_ttl'),
            fn () => $this->buildGrants($user),
        );
    }

    /**
     * True while inside an application DB transaction. Tests wrap everything in
     * one outer transaction, so they raise the baseline to 1.
     */
    private function inTransaction(): bool
    {
        return DB::transactionLevel() > (int) config('mpclg.transaction_baseline', 0);
    }

    public function flushMemo(): void
    {
        $this->memo = [];
        $this->departmentMap = null;
    }

    /**
     * @return array<string, array{enabled: bool, module_enabled: bool, sensitive: bool}>
     */
    public function permissionMeta(): array
    {
        $key = 'access.permission_meta.g'.$this->cache->globalVersion();
        $build = fn () => Permission::query()
            ->join('modules', 'modules.id', '=', 'permissions.module_id')
            ->get(['permissions.code', 'permissions.is_enabled', 'permissions.is_sensitive', 'modules.is_enabled as module_enabled'])
            ->mapWithKeys(fn ($p) => [$p->code => [
                'enabled' => (bool) $p->is_enabled,
                'module_enabled' => (bool) $p->module_enabled,
                'sensitive' => (bool) $p->is_sensitive,
            ]])->all();

        if ($this->inTransaction()) {
            return $build();
        }

        return Cache::remember($key, config('mpclg.permission_cache_ttl'), $build);
    }

    /** @return array<string, array<int, array>> */
    private function buildGrants(User $user, bool $includeDelegations = true): array
    {
        $grants = [];

        $overrides = UserPermission::query()
            ->open()
            ->where('user_id', $user->id)
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->get(['user_permissions.*', 'permissions.code as permission_code']);

        foreach ($overrides as $o) {
            $grants[$o->permission_code][] = $this->grant('override', $o->id, 'override#'.$o->id, $o->effect->value, $o->scope_type, $o->scope_id, $o->valid_from, $o->valid_to);
        }

        $assignments = UserRole::query()
            ->open()
            ->where('user_roles.user_id', $user->id)
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereNull('roles.deleted_at')
            ->where('roles.is_active', true)
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->get(['user_roles.*', 'roles.code as role_code', 'permissions.code as permission_code']);

        foreach ($assignments as $a) {
            $grants[$a->permission_code][] = $this->grant('role', $a->id, $a->role_code, PermissionEffect::Allow->value, $a->scope_type, $a->scope_id, $a->valid_from, $a->valid_to);
        }

        if ($includeDelegations) {
            foreach ($this->delegatedGrants($user) as $code => $list) {
                foreach ($list as $g) {
                    $grants[$code][] = $g;
                }
            }
        }

        return $grants;
    }

    /** Grants the user receives as a delegate. Delegations never chain. */
    private function delegatedGrants(User $delegate): array
    {
        $delegations = Delegation::query()
            ->where('delegate_user_id', $delegate->id)
            ->where('status', 'active')
            ->where('valid_to', '>', now())
            ->with(['delegator', 'permission'])
            ->get();

        $result = [];
        $meta = $this->permissionMeta();

        foreach ($delegations as $d) {
            $delegator = $d->delegator;
            if (! $delegator || ! $delegator->isActive()) {
                continue;
            }

            // What the delegator holds themselves (roles + allow overrides, no delegations).
            $delegatorGrants = $this->buildGrants($delegator, includeDelegations: false);

            $codes = $d->permission
                ? [$d->permission->code]
                : array_keys(array_filter($meta, fn ($m) => $m['sensitive']));

            foreach ($codes as $code) {
                $held = array_filter($delegatorGrants[$code] ?? [], fn ($g) => $g['effect'] === 'allow' && $this->isCurrent($g));
                $denied = array_filter($delegatorGrants[$code] ?? [], fn ($g) => $g['effect'] === 'deny' && $this->isCurrent($g));
                if ($held === [] || $denied !== []) {
                    continue;
                }

                // Delegation scope: the delegation's own scope if set, otherwise the delegator's.
                $scopes = $d->scope_type === ScopeType::Global
                    ? array_map(fn ($g) => [$g['scope_type'], $g['scope_id']], $held)
                    : [[$d->scope_type->value, $d->scope_id]];

                foreach ($scopes as [$type, $id]) {
                    $result[$code][] = $this->grant('delegation', $d->id, 'delegation#'.$d->id, 'allow', $type, $id, $d->valid_from, $d->valid_to);
                }
            }
        }

        return $result;
    }

    private function grant(string $source, int $sourceId, string $label, string $effect, ScopeType|string|null $scopeType, ?int $scopeId, $validFrom, $validTo): array
    {
        $scopeType = $scopeType instanceof ScopeType ? $scopeType->value : ($scopeType ?? 'global');

        return [
            'source' => $source,
            'source_id' => $sourceId,
            'label' => $label,
            'effect' => $effect,
            'scope_type' => $scopeType,
            'scope_id' => $scopeType === 'global' ? null : $scopeId,
            'valid_from' => $validFrom?->getTimestamp(),
            'valid_to' => $validTo?->getTimestamp(),
        ];
    }

    private function isCurrent(array $grant): bool
    {
        $now = now()->getTimestamp();

        return ($grant['valid_from'] === null || $grant['valid_from'] <= $now)
            && ($grant['valid_to'] === null || $grant['valid_to'] > $now);
    }

    /** Does the grant's scope include the requested scope? */
    private function covers(array $grant, Scope $requested): bool
    {
        $type = $grant['scope_type'];
        $id = $grant['scope_id'];

        if ($type === 'global') {
            return true;
        }
        if ($requested->isGlobal()) {
            return false;
        }
        if ($type === $requested->type->value && $id === $requested->id) {
            return true;
        }

        if ($requested->type === ScopeType::Department) {
            $dept = $this->departments()[$requested->id] ?? null;
            if ($dept === null) {
                return false;
            }
            if ($type === 'division') {
                return $dept['division_id'] === $id;
            }
            if ($type === 'department') {
                return $dept['parent_department_id'] === $id;
            }
        }

        return false;
    }

    private function departments(): array
    {
        return $this->departmentMap ??= Department::withTrashed()
            ->get(['id', 'division_id', 'parent_department_id'])
            ->mapWithKeys(fn ($d) => [$d->id => [
                'division_id' => (int) $d->division_id,
                'parent_department_id' => $d->parent_department_id ? (int) $d->parent_department_id : null,
            ]])->all();
    }

    private function reasonFor(array $grants): string
    {
        $sources = array_unique(array_column($grants, 'source'));

        return match (true) {
            in_array('override', $sources, true) => 'allowed_by_override',
            in_array('role', $sources, true) => 'allowed_by_role',
            default => 'allowed_by_delegation',
        };
    }

    private function decision(bool $allowed, string $reason, array $sources = []): array
    {
        return ['allowed' => $allowed, 'reason' => $reason, 'sources' => array_values($sources)];
    }
}
