<?php

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\StockCountSession;
use App\Services\Stock\CountService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockCountController extends Controller
{
    use ScopesListings;

    public function __construct(private readonly CountService $counts) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->limitToStores(StockCountSession::query()->with(['store:id,code,name', 'countedBy:id,full_name'])->withCount('lines'), 'stock.view');

        $page = ListQuery::paginate($query, $request,
            filters: ['status' => 'status', 'store_id' => 'store_id'],
            sorts: ['id', 'session_no', 'cut_off_at'],
            search: ['session_no', 'scope_note'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(StockCountSession $stockCount): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($stockCount->load(['store', 'countedBy', 'lines.item:id,item_code,name_en'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
            'scope_note' => ['nullable', 'string', 'max:255'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->counts->create($data)), 'Count sheet created from the ledger balances.');
    }

    public function record(Request $request, StockCountSession $stockCount): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.counted_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.recount_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::success(new MasterRecordResource($this->counts->record($stockCount, $data['lines'])), 'Counts saved.');
    }

    public function submit(StockCountSession $stockCount): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($this->counts->submit($stockCount)->load('lines.item')), 'Count submitted for independent approval.');
    }

    public function approve(Request $request, StockCountSession $stockCount): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:255']])['remarks'] ?? null;

        return ApiResponse::success(new MasterRecordResource($this->counts->approve($stockCount, $remarks)->load('lines.item')), 'Count approved. Variances were raised as stock adjustments for approval.');
    }
}
