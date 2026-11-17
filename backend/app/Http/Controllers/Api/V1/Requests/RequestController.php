<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\GoodsRequest;
use App\Services\Access\Actor;
use App\Services\Approvals\ApprovalService;
use App\Services\Requests\RequestService;
use App\Services\Stock\LedgerService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    use ScopesListings;

    private const WITH = ['department:id,code,name_en', 'store:id,code,name', 'submittedBy:id,full_name', 'certifiedBy:id,full_name'];

    public function __construct(
        private readonly RequestService $requests,
        private readonly ApprovalService $approvals,
        private readonly LedgerService $ledger,
        private readonly Actor $actor,
    ) {}

    /**
     * request.view_all sees every request (store-scoped for storekeepers),
     * request.view_department sees its department(s), request.view_own sees own.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GoodsRequest::query()->with(self::WITH)->withCount('allLines');

        if ($this->actor->can('request.view_all')) {
            $this->limitToStores($query, 'request.view_all');
        } elseif ($this->actor->can('request.view_department')) {
            $this->limitToDepartments($query, 'request.view_department');
        } else {
            $query->where('submitted_by_officer_id', $this->actor->user()->officer_id ?? 0);
        }

        $page = ListQuery::paginate($query, $request,
            filters: [
                'status' => 'status',
                'request_type' => 'request_type',
                'department_id' => 'department_id',
                'store_id' => 'store_id',
                'receipt_status' => 'receipt_status',
                'awaiting' => fn ($q, $v) => match ($v) {
                    'certify' => $q->where('status', 'submitted'),
                    'approve' => $q->where('status', 'under_review'),
                    'issue' => $q->whereIn('status', GoodsRequest::OPEN_FOR_ISSUE),
                    default => $q,
                },
            ],
            sorts: ['id', 'request_no', 'created_at', 'submitted_at', 'required_on'],
            search: ['request_no', 'purpose', 'department_name_snapshot'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(GoodsRequest $goodsRequest): JsonResponse
    {
        return ApiResponse::success($this->present($goodsRequest));
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->requests->create($this->validated($request, true));

        return ApiResponse::created($this->present($result), "Request {$result->request_no} saved as draft.");
    }

    public function update(Request $request, GoodsRequest $goodsRequest): JsonResponse
    {
        return ApiResponse::success($this->present($this->requests->update($goodsRequest, $this->validated($request, false))), 'Request updated.');
    }

    public function submit(GoodsRequest $goodsRequest): JsonResponse
    {
        return ApiResponse::success($this->present($this->requests->submit($goodsRequest)), 'Request submitted for certification.');
    }

    public function certify(Request $request, GoodsRequest $goodsRequest): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:500']])['remarks'] ?? null;

        return ApiResponse::success($this->present($this->requests->certify($goodsRequest, $remarks)), 'Request certified.');
    }

    public function approve(Request $request, GoodsRequest $goodsRequest): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.approved_qty' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success($this->present($this->requests->approve($goodsRequest, $data['lines'] ?? [], $data['remarks'] ?? null)), 'Request approved; stock reserved.');
    }

    public function returnForClarification(Request $request, GoodsRequest $goodsRequest): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['required', 'string', 'min:3', 'max:500']])['remarks'];

        return ApiResponse::success($this->present($this->requests->returnForClarification($goodsRequest, $remarks)), 'Request returned for clarification.');
    }

    public function reject(Request $request, GoodsRequest $goodsRequest): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['required', 'string', 'min:3', 'max:500']])['remarks'];

        return ApiResponse::success($this->present($this->requests->reject($goodsRequest, $remarks)), 'Request rejected.');
    }

    public function cancel(Request $request, GoodsRequest $goodsRequest): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];

        return ApiResponse::success($this->present($this->requests->cancel($goodsRequest, $reason)), 'Request cancelled.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $r = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'request_type' => [$creating ? 'nullable' : 'prohibited', Rule::in(['goods', 'a4_paper'])],
            'department_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'store_id' => [$r, 'integer', Rule::exists('stores', 'id')->whereNull('deleted_at')],
            'purpose' => ['nullable', 'string', 'max:255'],
            'required_on' => ['nullable', 'date'],
            'staffing_count' => ['nullable', 'integer', 'min:0'],
            'period_from_on' => ['nullable', 'date'],
            'period_to_on' => ['nullable', 'date', 'after_or_equal:period_from_on'],
            'paper_statement_id' => ['nullable', 'integer', Rule::exists('paper_statements', 'id')],
            'observation_ids' => ['nullable', 'array'],
            'observation_ids.*' => ['integer'],
            'lines' => [$r, 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'lines.*.unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'lines.*.requested_qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function present(GoodsRequest $request): array
    {
        $request->load([...self::WITH, 'allLines.item:id,item_code,name_en,tracking_method,base_unit_id', 'allLines.item.baseUnit:id,code', 'allLines.unit:id,code,name', 'issues:id,public_id,request_id,issue_no,status,issue_on', 'paperStatement', 'meterObservations.machine:id,code', 'a4Exceptions']);
        $data = (new MasterRecordResource($request))->resolve(request());
        unset($data['all_lines']);

        $data['lines'] = $request->currentLinesLoaded()->map(fn ($l) => (new MasterRecordResource($l))->resolve(request()) + [
            'outstanding_qty' => $l->outstandingQty(),
            'available_in_store' => $this->ledger->available($l->item_id, $request->store_id),
            'base_unit' => $l->item?->baseUnit?->code,
        ])->all();
        $data['previous_versions'] = $request->allLines->where('version_no', '<', $request->current_version_no)->groupBy('version_no')->keys()->values();
        $data['approvals'] = $this->approvals->history($request);
        $data['current_step'] = $this->approvals->current($request, $request->current_version_no)?->step?->only(['step_no', 'name']);

        return $data;
    }
}
