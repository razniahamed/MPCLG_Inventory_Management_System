<?php

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\StockAdjustment;
use App\Services\Stock\AdjustmentService;
use App\Support\ApiResponse;
use App\Support\Idempotency;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockAdjustmentController extends Controller
{
    use ScopesListings;

    private const WITH = ['item:id,item_code,name_en', 'store:id,code,name', 'recordedBy:id,full_name', 'approvedBy:id,full_name'];

    public function __construct(private readonly AdjustmentService $adjustments) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->limitToStores(StockAdjustment::query()->with(self::WITH), 'stock.view');

        $page = ListQuery::paginate($query, $request,
            filters: ['status' => 'status', 'store_id' => 'store_id', 'item_id' => 'item_id'],
            sorts: ['id', 'adjustment_no', 'created_at'],
            search: ['adjustment_no', 'reason'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($stockAdjustment->load(self::WITH)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'batch_no' => ['nullable', 'string', 'max:60'],
            'qty_delta' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->adjustments->create($data)->load(self::WITH)), 'Adjustment submitted for independent approval.');
    }

    public function approvePost(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:255']])['remarks'] ?? null;
        $adjustment = $this->adjustments->approvePost($stockAdjustment, Idempotency::keyFrom($request), $remarks);

        return ApiResponse::success(new MasterRecordResource($adjustment->load(self::WITH)), 'Adjustment approved and posted.');
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];

        return ApiResponse::success(new MasterRecordResource($this->adjustments->reject($stockAdjustment, $reason)->load(self::WITH)), 'Adjustment rejected.');
    }
}
