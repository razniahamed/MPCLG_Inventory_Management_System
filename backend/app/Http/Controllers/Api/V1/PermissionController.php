<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToggleEnabledRequest;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\PermissionResource;
use App\Models\Module;
use App\Models\Permission;
use App\Services\Access\AccessControlService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    /** The permission catalogue grouped by module. */
    public function index(): JsonResponse
    {
        $modules = Module::query()
            ->orderBy('sort_order')
            ->with(['permissions' => fn ($q) => $q->orderBy('code')->with('module')])
            ->get();

        return ApiResponse::success(ModuleResource::collection($modules));
    }

    /** Global on/off switch for one permission (affects every user). */
    public function setEnabled(ToggleEnabledRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->access->setPermissionEnabled($permission, $request->boolean('is_enabled'), $request->validated('reason'));

        return ApiResponse::success(
            new PermissionResource($permission->load('module')),
            "Permission '{$permission->code}' ".($permission->is_enabled ? 'enabled' : 'disabled').' for everyone.',
        );
    }
}
