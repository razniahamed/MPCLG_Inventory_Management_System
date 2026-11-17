<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleRequest;
use App\Http\Requests\Access\SyncRolePermissionsRequest;
use App\Http\Requests\ReasonRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Access\RoleService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->withCount(['assignments' => fn ($q) => $q->open()]);

        $page = ListQuery::paginate($query, $request,
            filters: ['is_active' => 'is_active', 'is_system' => 'is_system'],
            sorts: ['id', 'code', 'name', 'created_at'],
            search: ['code', 'name'],
            defaultSort: 'code',
        );

        return ApiResponse::paginated($page, RoleResource::class);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->roles->create($request->validated());

        return ApiResponse::created(new RoleResource($role->load('permissions')), 'Role created.');
    }

    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(new RoleResource($role->load('permissions')->loadCount(['assignments' => fn ($q) => $q->open()])));
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        return ApiResponse::success(new RoleResource($this->roles->update($role, $request->validated())->load('permissions')), 'Role updated.');
    }

    /** Delete a non-system role that nobody holds (soft delete). */
    public function destroy(ReasonRequest $request, Role $role): JsonResponse
    {
        $this->roles->delete($role, $request->validated('reason'));

        return ApiResponse::success(null, 'Role deleted.');
    }

    /** Replace the role's permission set. */
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->roles->syncPermissions($role, $request->validated('permissions'), $request->validated('reason'));

        return ApiResponse::success(new RoleResource($role), 'Role permissions updated.');
    }
}
