<?php

namespace App\Http\Controllers\Api\V1\Receiving;

use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Procurement\PurchaseOrderService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(PurchaseOrder::query()->with('supplier:id,code,name')->withCount('lines'), $request,
            filters: ['status' => 'status', 'supplier_id' => 'supplier_id'],
            sorts: ['id', 'po_number', 'po_date', 'created_at'],
            search: ['po_number', 'approval_doc_ref'],
            defaultSort: '-po_date',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['supplier', 'lines.item', 'lines.unit']);
        $data = (new MasterRecordResource($purchaseOrder))->resolve(request());
        $data['lines'] = $purchaseOrder->lines->map(fn (PurchaseOrderLine $l) => (new MasterRecordResource($l))->resolve(request()) + [
            'received_qty' => $l->receivedQty(),
            'outstanding_qty' => $l->outstandingQty(),
        ])->all();

        return ApiResponse::success($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'po_number' => ['required', 'string', 'max:40'],
            'po_date' => ['required', 'date'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'approval_doc_ref' => ['nullable', 'string', 'max:190'],
            'expected_delivery_on' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'lines.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'lines.*.ordered_qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax' => ['nullable', 'numeric', 'min:0'],
            'lines.*.other_charges' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->orders->create($data)), 'Purchase order recorded.');
    }

    public function setStatus(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['closed', 'cancelled'])],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        return ApiResponse::success(new MasterRecordResource($this->orders->setStatus($purchaseOrder, $data['status'], $data['reason'])), 'Purchase order updated.');
    }
}
