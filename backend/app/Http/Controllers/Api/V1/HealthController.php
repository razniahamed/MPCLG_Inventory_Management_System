<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Service health check (public).
     *
     * @unauthenticated
     */
    public function __invoke(): JsonResponse
    {
        $database = rescue(fn () => DB::select('select 1') !== [], false, false);

        return ApiResponse::success([
            'status' => $database ? 'ok' : 'degraded',
            'database' => $database ? 'ok' : 'unreachable',
            'version' => 'v1',
            'time' => now()->toIso8601String(),
        ], 'Service is running.', $database ? 200 : 503);
    }
}
