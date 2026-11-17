<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Department;
use App\Services\Access\Actor;
use App\Services\Access\PermissionResolver;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Row-level scope for lists: a Storekeeper sees only their store(s), a Chief
 * Clerk only their department (Section 5.4 rule 3).
 */
trait ScopesListings
{
    protected function limitToStores(Builder $query, string $permission, string $column = 'store_id'): Builder
    {
        $ids = app(Actor::class)->scopeIds($permission, 'store');

        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }

    protected function limitToDepartments(Builder $query, string $permission, string $column = 'department_id'): Builder
    {
        $ids = $this->departmentIds($permission);

        return $ids === null ? $query : $query->whereIn($column, $ids ?: [0]);
    }

    /** @return array<int, int>|null null = everywhere */
    protected function departmentIds(string $permission): ?array
    {
        $user = app(Actor::class)->user();
        $ids = [];

        foreach (app(PermissionResolver::class)->scopesFor($user, $permission) as $scope) {
            if ($scope->isGlobal()) {
                return null;
            }
            if ($scope->type->value === 'department') {
                $ids[] = $scope->id;
                $ids = array_merge($ids, Department::where('parent_department_id', $scope->id)->pluck('id')->all());
            }
            if ($scope->type->value === 'division') {
                $ids = array_merge($ids, Department::where('division_id', $scope->id)->pluck('id')->all());
            }
        }

        return array_values(array_unique($ids));
    }
}
