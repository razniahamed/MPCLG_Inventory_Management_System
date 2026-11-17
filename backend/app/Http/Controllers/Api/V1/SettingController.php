<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UpdateSettingsRequest;
use App\Models\SystemSetting;
use App\Services\System\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): JsonResponse
    {
        $stored = SystemSetting::all()->keyBy('key');

        $rows = collect(SettingsService::DEFINITIONS)->map(fn ($def, $key) => [
            'key' => $key,
            'group' => $def[0],
            'type' => $def[1]->value,
            'value' => isset($stored[$key]) ? $stored[$key]->typedValue() : $def[2],
            'default' => $def[2],
            'description' => $def[3],
            'updated_at' => $stored->get($key)?->updated_at?->toIso8601String(),
        ])->values();

        return ApiResponse::success($rows);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->settingValues(), $request->validated('reason'));

        return $this->index()->setStatusCode(200);
    }
}
