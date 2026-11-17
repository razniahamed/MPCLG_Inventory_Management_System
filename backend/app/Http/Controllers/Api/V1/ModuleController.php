<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToggleEnabledRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use App\Services\Access\AccessControlService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(ModuleResource::collection(Module::orderBy('sort_order')->get()));
    }

    /** Switch a whole module on/off (e.g. for phased go-live). */
    public function setEnabled(ToggleEnabledRequest $request, Module $module): JsonResponse
    {
        $module = $this->access->setModuleEnabled($module, $request->boolean('is_enabled'), $request->validated('reason'));

        return ApiResponse::success(
            new ModuleResource($module),
            "Module '{$module->code}' ".($module->is_enabled ? 'enabled.' : 'disabled.'),
        );
    }
}
