<?php

namespace App\Http\Controllers\Api\V1\Receiving;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\SupplierReturn;
use App\Services\Procurement\SupplierReturnService;
use App\Support\ApiResponse;
use App\Support\Idempotency;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierReturnController extends Controller
{
    use ScopesListings;

    public function __construct(private readonly SupplierReturnService $returns) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->limitToStores(SupplierReturn::query()->with(['supplier:id,code,name', 'store:id,code,name', 'grn:id,public_id,grn_no']), 'supplier_return.manage');

        $page = ListQuery::paginate($query, $request,
            filters: ['status' => 'status', 'supplier_id' => 'supplier_id', 'store_id' => 'store_id'],
            sorts: ['id', 'return_no', 'return_on'],
            search: ['return_no', 'reason'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(SupplierReturn $supplierReturn): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($supplierReturn->load(['supplier', 'store', 'grn', 'lines.item', 'lines.unit'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grn_id' => ['required', 'integer', Rule::exists('grns', 'id')],
            'return_on' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'replacement_or_claim_ref' => ['nullable', 'string', 'max:190'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.grn_line_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->returns->create($data)->load('lines.item')), 'Supplier return recorded.');
    }

    public function post(Request $request, SupplierReturn $supplierReturn): JsonResponse
    {
        $return = $this->returns->post($supplierReturn, Idempotency::keyFrom($request));

        return ApiResponse::success(new MasterRecordResource($return->load('lines.item')), 'Supplier return posted.');
    }
}
