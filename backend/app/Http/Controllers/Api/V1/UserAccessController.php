<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\AssignRolesRequest;
use App\Http\Requests\Access\SetUserPermissionRequest;
use App\Http\Resources\UserPermissionResource;
use App\Http\Resources\UserRoleResource;
use App\Models\Permission;
use App\Models\User;
use App\Services\Access\PermissionResolver;
use App\Services\Access\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A user's role assignments, per-user overrides and effective permissions.
 */
class UserAccessController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /** Role assignments (current and, with ?history=1, ended ones too). */
    public function roles(Request $request, User $user): JsonResponse
    {
        $query = $user->roleAssignments()->with('role')->orderByDesc('valid_from');
        if (! $request->boolean('history')) {
            $query->open();
        }

        return ApiResponse::success(UserRoleResource::collection($query->get()));
    }

    /** Replace or merge role assignments (with scope and validity). */
    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        $this->users->assignRoles($user, $request->validated('roles'), $request->validated('mode'), $request->validated('reason'));

        return ApiResponse::success(
            UserRoleResource::collection($user->roleAssignments()->open()->with('role')->get()),
            'Roles updated.',
        );
    }

    /** Per-user overrides (current and, with ?history=1, revoked / expired). */
    public function permissions(Request $request, User $user): JsonResponse
    {
        $query = $user->permissionOverrides()->with('permission')->orderByDesc('id');
        if (! $request->boolean('history')) {
            $query->open();
        }

        return ApiResponse::success(UserPermissionResource::collection($query->get()));
    }

    /** Turn ONE permission allow / deny / inherit for ONE user. */
    public function setPermission(SetUserPermissionRequest $request, User $user, Permission $permission): JsonResponse
    {
        $override = $this->users->setPermissionOverride($user, $permission, $request->validated('effect'), $request->validated());

        return ApiResponse::success(
            $override ? new UserPermissionResource($override->load('permission')) : null,
            $override ? "Permission '{$permission->code}' set to {$override->effect->value}." : "Permission '{$permission->code}' now follows the user's roles.",
        );
    }

    /** Why a user can / cannot do each thing (shows role / override / delegation sources). */
    public function effectivePermissions(User $user, PermissionResolver $resolver): JsonResponse
    {
        $rows = Permission::query()->with('module')->orderBy('code')->get()->map(function (Permission $permission) use ($user, $resolver) {
            $decision = $resolver->explain($user, $permission->code);

            return [
                'code' => $permission->code,
                'module' => $permission->module->code,
                'allowed' => $decision['allowed'],
                'reason' => $decision['reason'],
                'sources' => array_map(fn ($g) => [
                    'source' => $g['source'],
                    'label' => $g['label'],
                    'effect' => $g['effect'],
                    'scope_type' => $g['scope_type'],
                    'scope_id' => $g['scope_id'],
                    'valid_to' => $g['valid_to'] ? date(DATE_ATOM, $g['valid_to']) : null,
                ], $decision['sources']),
            ];
        });

        return ApiResponse::success([
            'user' => ['id' => $user->public_id, 'username' => $user->username, 'status' => $user->status->value],
            'permissions' => $rows,
        ]);
    }
}
