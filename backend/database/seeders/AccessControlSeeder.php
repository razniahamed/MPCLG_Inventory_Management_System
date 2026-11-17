<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\Catalogue;
use Illuminate\Database\Seeder;

/**
 * Modules, the permission catalogue (5.3) and the starting roles (5.5).
 * Idempotent: safe to re-run. Role permission sets are only seeded when the
 * role is first created, so admin edits are not overwritten.
 */
class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [];
        foreach (Catalogue::MODULES as $code => [$name, $sort]) {
            $modules[$code] = Module::updateOrCreate(['code' => $code], ['name' => $name, 'sort_order' => $sort]);
        }

        foreach (Catalogue::PERMISSIONS as $moduleCode => $codes) {
            foreach ($codes as $code) {
                [$resource, $action] = explode('.', $code, 2);
                Permission::updateOrCreate(['code' => $code], [
                    'module_id' => $modules[$moduleCode]->id,
                    'name' => Catalogue::displayName($code),
                    'resource' => $resource,
                    'action' => $action,
                    'is_sensitive' => in_array($code, Catalogue::SENSITIVE, true),
                    'supports_scope' => in_array($moduleCode, Catalogue::SCOPED_MODULES, true),
                ]);
            }
        }

        $permissionIds = Permission::pluck('id', 'code');

        foreach (Catalogue::roles() as $code => [$name, $description, $entries]) {
            $role = Role::firstOrNew(['code' => $code]);
            $isNew = ! $role->exists;
            $role->fill(['name' => $name, 'description' => $description, 'is_system' => true])->save();

            if ($isNew) {
                $ids = collect(Catalogue::expand($entries))->map(fn ($c) => $permissionIds[$c])->all();
                $role->permissions()->sync(
                    collect($ids)->mapWithKeys(fn ($id) => [$id => ['granted_at' => now()]])->all()
                );
            }
        }
    }
}
