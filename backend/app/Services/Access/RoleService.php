<?php

namespace App\Services\Access;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRole;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccessCache $cache,
        private readonly AdminGuard $adminGuard,
    ) {}

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $this->audit->log(AuditAction::Created, $role, null, $role->only(['code', 'name', 'description']), $data['reason'] ?? null);

            if (! empty($data['permissions'])) {
                $this->syncPermissions($role, $data['permissions'], $data['reason'] ?? 'Initial permissions.');
            }

            return $role->fresh();
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $original = $role->getOriginal();
            $role->fill(array_intersect_key($data, array_flip(['name', 'description', 'is_active'])))->save();

            if ($role->wasChanged('is_active')) {
                $this->invalidateHolders($role);
                $this->adminGuard->assertAdministratorsRemain();
            }

            $this->audit->logChanges($role, $original, $data['reason'] ?? null);

            return $role;
        });
    }

    public function delete(Role $role, string $reason): void
    {
        if ($role->is_system) {
            throw ApiException::businessRule('Seeded system roles cannot be deleted (they can be edited).');
        }

        if (UserRole::query()->open()->where('role_id', $role->id)->exists()) {
            throw ApiException::businessRule('The role is still assigned to users. Remove the assignments first.');
        }

        DB::transaction(function () use ($role, $reason) {
            $role->forceFill(['is_active' => false])->save();
            $role->delete();
            $this->audit->log(AuditAction::Deleted, $role, $role->only(['code', 'name']), null, $reason);
        });
    }

    /**
     * Replace the role's permission set.
     *
     * @param  array<int, string>  $codes
     */
    public function syncPermissions(Role $role, array $codes, string $reason): Role
    {
        $permissions = Permission::whereIn('code', $codes)->get();

        $unknown = array_values(array_diff($codes, $permissions->pluck('code')->all()));
        if ($unknown !== []) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                'permissions' => ['Unknown permission codes: '.implode(', ', $unknown).'.'],
            ]);
        }

        if ($role->isNonApproving()) {
            $sensitive = $permissions->where('is_sensitive', true)->pluck('code')->all();
            if ($sensitive !== []) {
                throw ApiException::businessRule(
                    "The '{$role->code}' role must not hold approval or financial permissions: ".implode(', ', $sensitive).'.'
                );
            }
        }

        return DB::transaction(function () use ($role, $permissions, $reason) {
            $before = $role->permissions()->pluck('code')->sort()->values()->all();

            $now = now();
            $actorId = Auth::id();
            $existing = $role->permissions()->pluck('permissions.id')->all();

            $role->permissions()->sync(
                $permissions->mapWithKeys(fn ($p) => [$p->id => in_array($p->id, $existing, true)
                    ? []
                    : ['granted_by' => $actorId, 'granted_at' => $now]])->all()
            );

            $after = $permissions->pluck('code')->sort()->values()->all();

            $this->invalidateHolders($role);
            $this->adminGuard->assertAdministratorsRemain();

            $this->audit->log(AuditAction::PermissionChanged, $role, [
                'removed' => array_values(array_diff($before, $after)),
            ], [
                'added' => array_values(array_diff($after, $before)),
            ], $reason);

            return $role->fresh('permissions');
        });
    }

    private function invalidateHolders(Role $role): void
    {
        $userIds = UserRole::query()->open()->where('role_id', $role->id)->pluck('user_id');
        $this->cache->invalidateUsers($userIds);
    }
}
