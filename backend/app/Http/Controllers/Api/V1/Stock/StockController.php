<?php

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only stock views: balances (calculated from the ledger) and the ledger itself. */
class StockController extends Controller
{
    use ScopesListings;

    public function balances(Request $request): JsonResponse
    {
        $query = StockBalance::query()
            ->with(['item:id,item_code,name_en,reorder_level,tracking_method,base_unit_id,category_id', 'item.baseUnit:id,code', 'store:id,code,name'])
            ->select('stock_balances.*', DB::raw('(on_hand_usable - reserved) AS available'));
        $this->limitToStores($query, 'stock.view');

        $page = ListQuery::paginate($query, $request,
            filters: [
                'store_id' => 'store_id',
                'item_id' => 'item_id',
                'category_id' => fn ($q, $v) => $q->whereHas('item', fn ($i) => $i->where('category_id', $v)),
                'low_stock' => fn ($q, $v) => $v === 'true' ? $q->whereHas('item', fn ($i) => $i->whereNotNull('reorder_level')->whereColumn('items.reorder_level', '>=', 'stock_balances.on_hand_usable')) : $q,
                'in_stock' => fn ($q, $v) => $v === 'true' ? $q->where('on_hand_usable', '>', 0) : $q,
                'item' => fn ($q, $v) => $q->whereHas('item', fn ($i) => $i->where('item_code', 'like', "%{$v}%")->orWhere('name_en', 'like', "%{$v}%")),
            ],
            sorts: ['id', 'on_hand_usable', 'reserved', 'updated_at'],
            search: [],
            defaultSort: '-on_hand_usable',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function ledger(Request $request): JsonResponse
    {
        $query = StockLedger::query()->with(['item:id,item_code,name_en', 'store:id,code,name', 'officer:id,full_name']);
        $this->limitToStores($query, 'ledger.view');

        $page = ListQuery::paginate($query, $request,
            filters: [
                'store_id' => 'store_id',
                'item_id' => 'item_id',
                'movement_type' => 'movement_type',
                'source_type' => 'source_type',
                'source_id' => 'source_id',
                'from' => fn ($q, $v) => $q->where('event_on', '>=', $v),
                'to' => fn ($q, $v) => $q->where('event_on', '<=', $v),
            ],
            sorts: ['id', 'event_on', 'created_at'],
            search: ['document_no', 'remarks'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }
}
