<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** Search the audit trail. */
    public function index(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(AuditLog::query()->with('user'), $request,
            filters: [
                'action' => 'action',
                'auditable_type' => 'auditable_type',
                'auditable_id' => 'auditable_id',
                'request_id' => 'request_id',
                'user' => fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('public_id', $v)),
                'from' => fn ($q, $v) => $q->where('event_at', '>=', $v),
                'to' => fn ($q, $v) => $q->where('event_at', '<=', $v),
            ],
            sorts: ['id', 'event_at'],
            search: ['reason'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, AuditLogResource::class);
    }
}
