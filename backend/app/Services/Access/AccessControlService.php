<?php

namespace App\Services\Access;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Module;
use App\Models\Permission;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Global switches: a whole module, or one permission for everyone.
 */
class AccessControlService
{
    /** Switching these off could lock every administrator out. */
    private const PROTECTED_PERMISSIONS = [
        'role.manage', 'permission.override_user', 'permission.toggle_global', 'module.toggle',
    ];

    private const PROTECTED_MODULES = ['access'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccessCache $cache,
    ) {}

    public function setModuleEnabled(Module $module, bool $enabled, string $reason): Module
    {
        if (! $enabled && in_array($module->code, self::PROTECTED_MODULES, true)) {
            throw ApiException::businessRule("The '{$module->code}' module cannot be switched off.");
        }

        return DB::transaction(function () use ($module, $enabled, $reason) {
            $old = $module->is_enabled;
            $module->update(['is_enabled' => $enabled]);
            $this->audit->log(AuditAction::PermissionChanged, $module, ['is_enabled' => $old], ['is_enabled' => $enabled], $reason);
            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $module;
        });
    }

    public function setPermissionEnabled(Permission $permission, bool $enabled, string $reason): Permission
    {
        if (! $enabled && in_array($permission->code, self::PROTECTED_PERMISSIONS, true)) {
            throw ApiException::businessRule("The '{$permission->code}' permission cannot be switched off globally.");
        }

        return DB::transaction(function () use ($permission, $enabled, $reason) {
            $old = $permission->is_enabled;
            $permission->update(['is_enabled' => $enabled]);
            $this->audit->log(AuditAction::PermissionChanged, $permission, ['is_enabled' => $old], ['is_enabled' => $enabled], $reason);
            DB::afterCommit(fn () => $this->cache->bumpGlobal());

            return $permission;
        });
    }
}
