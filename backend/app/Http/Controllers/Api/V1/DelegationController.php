<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\DelegationRequest;
use App\Http\Requests\ReasonRequest;
use App\Http\Resources\DelegationResource;
use App\Models\Delegation;
use App\Services\Access\DelegationService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DelegationController extends Controller
{
    public function __construct(private readonly DelegationService $delegations) {}

    public function index(Request $request): JsonResponse
    {
        $query = Delegation::query()->with(['delegator', 'delegate', 'permission']);

        $page = ListQuery::paginate($query, $request,
            filters: [
                'status' => 'status',
                'delegator' => fn ($q, $v) => $q->whereHas('delegator', fn ($u) => $u->where('public_id', $v)),
                'delegate' => fn ($q, $v) => $q->whereHas('delegate', fn ($u) => $u->where('public_id', $v)),
            ],
            sorts: ['id', 'valid_from', 'valid_to', 'created_at'],
            search: ['written_reference'],
            defaultSort: '-valid_from',
        );

        return ApiResponse::paginated($page, DelegationResource::class);
    }

    public function store(DelegationRequest $request): JsonResponse
    {
        $delegation = $this->delegations->create($request->validated());

        return ApiResponse::created(new DelegationResource($delegation->load(['delegator', 'delegate', 'permission'])), 'Delegation recorded.');
    }

    public function show(Delegation $delegation): JsonResponse
    {
        return ApiResponse::success(new DelegationResource($delegation->load(['delegator', 'delegate', 'permission'])));
    }

    /** Shorten / extend the end date or correct the written reference. */
    public function update(DelegationRequest $request, Delegation $delegation): JsonResponse
    {
        $delegation = $this->delegations->update($delegation, $request->validated());

        return ApiResponse::success(new DelegationResource($delegation->load(['delegator', 'delegate', 'permission'])), 'Delegation updated.');
    }

    /** Revoke (records are kept; nothing is deleted). */
    public function destroy(ReasonRequest $request, Delegation $delegation): JsonResponse
    {
        $delegation = $this->delegations->revoke($delegation, $request->validated('reason'));

        return ApiResponse::success(new DelegationResource($delegation->load(['delegator', 'delegate', 'permission'])), 'Delegation revoked.');
    }
}
