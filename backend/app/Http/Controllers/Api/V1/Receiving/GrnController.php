<?php

namespace App\Http\Controllers\Api\V1\Receiving;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\Grn;
use App\Services\Approvals\ApprovalService;
use App\Services\Procurement\GrnService;
use App\Support\ApiResponse;
use App\Support\Idempotency;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GrnController extends Controller
{
    use ScopesListings;

    private const WITH = ['supplier:id,code,name', 'store:id,code,name', 'purchaseOrder:id,public_id,po_number', 'receivedBy:id,full_name', 'inspectedBy:id,full_name', 'authorisedBy:id,full_name'];

    public function __construct(private readonly GrnService $grns, private readonly ApprovalService $approvals) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->limitToStores(Grn::query()->with(self::WITH), 'grn.view');

        $page = ListQuery::paginate($query, $request,
            filters: ['status' => 'status', 'store_id' => 'store_id', 'supplier_id' => 'supplier_id', 'receipt_authority_type' => 'receipt_authority_type'],
            sorts: ['id', 'grn_no', 'receipt_on', 'created_at'],
            search: ['grn_no', 'invoice_no', 'delivery_note_no'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(Grn $grn): JsonResponse
    {
        return ApiResponse::success($this->present($grn));
    }

    public function store(Request $request): JsonResponse
    {
        $grn = $this->grns->create($this->validated($request, true));

        return ApiResponse::created($this->present($grn), "GRN {$grn->grn_no} saved as draft.");
    }

    public function update(Request $request, Grn $grn): JsonResponse
    {
        return ApiResponse::success($this->present($this->grns->updateDraft($grn, $this->validated($request, false))), 'GRN updated.');
    }

    public function submit(Grn $grn): JsonResponse
    {
        return ApiResponse::success($this->present($this->grns->submit($grn)), 'Submitted for inspection.');
    }

    public function inspect(Request $request, Grn $grn): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.accepted_qty' => ['required', 'numeric', 'min:0'],
            'lines.*.rejected_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.condition_note' => ['nullable', 'string', 'max:255'],
            'lines.*.rejection_reason' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::success($this->present($this->grns->inspect($grn, $data['lines'], $data['remarks'] ?? null)), 'Inspection recorded.');
    }

    public function approvePost(Request $request, Grn $grn): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:500']])['remarks'] ?? null;
        $grn = $this->grns->approvePost($grn, Idempotency::keyFrom($request), $remarks);

        return ApiResponse::success($this->present($grn), "GRN {$grn->grn_no} posted to stock.");
    }

    public function reject(Request $request, Grn $grn): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];

        return ApiResponse::success($this->present($this->grns->reject($grn, $reason)), 'GRN rejected.');
    }

    public function reverse(Request $request, Grn $grn): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];

        return ApiResponse::success($this->present($this->grns->reverse($grn, $reason)), 'GRN reversed.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $r = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'receipt_authority_type' => [$creating ? 'required' : 'prohibited', Rule::in(['po', 'donation', 'transfer_in', 'opening_balance'])],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'receipt_authority_ref' => ['nullable', 'string', 'max:190'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'store_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('stores', 'id')->whereNull('deleted_at')],
            'receipt_on' => [$r, 'date', 'before_or_equal:today'],
            'invoice_no' => ['nullable', 'string', 'max:60'],
            'invoice_on' => ['nullable', 'date'],
            'delivery_note_no' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => [$r, 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'lines.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'lines.*.po_line_id' => ['nullable', 'integer'],
            'lines.*.delivered_qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.batch_no' => ['nullable', 'string', 'max:60'],
            'lines.*.expiry_on' => ['nullable', 'date'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax' => ['nullable', 'numeric', 'min:0'],
            'lines.*.other_charges' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function present(Grn $grn): array
    {
        $grn->load([...self::WITH, 'lines.item:id,item_code,name_en,tracking_method', 'lines.unit:id,code,name']);
        $data = (new MasterRecordResource($grn))->resolve(request());
        $data['approvals'] = $this->approvals->history($grn);

        return $data;
    }
}
