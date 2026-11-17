<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\AssetTransfer;
use App\Services\Approvals\ApprovalService;
use App\Services\Assets\TransferService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransferController extends Controller
{
    private const WITH = ['asset:id,public_id,asset_no,item_id,operational_status', 'asset.item:id,name_en', 'fromDepartment:id,name_en', 'toDepartment:id,name_en', 'fromLocation:id,name', 'toLocation:id,name', 'toCustodian:id,full_name', 'requestedBy:id,full_name'];

    public function __construct(private readonly TransferService $transfers, private readonly ApprovalService $approvals) {}

    public function index(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(AssetTransfer::query()->with(self::WITH), $request,
            filters: [
                'status' => 'status',
                'transfer_type' => 'transfer_type',
                'asset_id' => 'asset_id',
                'to_department_id' => 'to_department_id',
                'from_department_id' => 'from_department_id',
                'overdue_loans' => fn ($q, $v) => $v === 'true' ? $q->where('transfer_type', 'loan')->where('status', 'received')->whereDate('expected_return_on', '<', now()) : $q,
            ],
            sorts: ['id', 'transfer_no', 'created_at'],
            search: ['transfer_no', 'authority_ref', 'reason'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(AssetTransfer $assetTransfer): JsonResponse
    {
        $data = (new MasterRecordResource($assetTransfer->load(self::WITH)))->resolve(request());
        $data['approvals'] = $this->approvals->history($assetTransfer);

        return ApiResponse::success($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required'],
            'transfer_type' => ['nullable', Rule::in(['transfer', 'loan'])],
            'to_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'to_location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'to_custodian_officer_id' => ['nullable', 'integer', Rule::exists('officers', 'id')],
            'accessories' => ['nullable', 'string', 'max:1000'],
            'authority_ref' => ['nullable', 'string', 'max:190'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expected_return_on' => ['nullable', 'date', 'after:today'],
        ]);

        $transfer = $this->transfers->create($data);

        return ApiResponse::created(new MasterRecordResource($transfer->load(self::WITH)), "Transfer {$transfer->transfer_no} submitted for approval.");
    }

    public function approve(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:255']])['remarks'] ?? null;

        return ApiResponse::success(new MasterRecordResource($this->transfers->approve($assetTransfer, $remarks)->load(self::WITH)), 'Transfer approved. The asset has not moved yet.');
    }

    public function reject(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['required', 'string', 'min:3', 'max:255']])['remarks'];

        return ApiResponse::success(new MasterRecordResource($this->transfers->reject($assetTransfer, $remarks)->load(self::WITH)), 'Transfer rejected.');
    }

    public function dispatch(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $data = $request->validate(['condition' => ['nullable', 'string', 'max:30'], 'accessories' => ['nullable', 'string', 'max:1000']]);

        return ApiResponse::success(new MasterRecordResource($this->transfers->dispatch($assetTransfer, $data)->load(self::WITH)), 'Dispatched. The asset is now In Transit.');
    }

    public function receive(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $data = $request->validate([
            'condition' => ['required', 'string', 'max:30'],
            'serial_verified' => ['required', 'accepted'],
        ]);

        return ApiResponse::success(new MasterRecordResource($this->transfers->receive($assetTransfer, $data)->load(self::WITH)), 'Received. Location and custodian updated.');
    }

    public function refuse(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];

        return ApiResponse::success(new MasterRecordResource($this->transfers->refuse($assetTransfer, $reason)->load(self::WITH)), 'Transfer refused; a discrepancy was opened.');
    }

    public function returnLoan(Request $request, AssetTransfer $assetTransfer): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:255']])['remarks'] ?? null;

        return ApiResponse::success(new MasterRecordResource($this->transfers->returnLoan($assetTransfer, $remarks)->load(self::WITH)), 'Loan returned.');
    }
}
