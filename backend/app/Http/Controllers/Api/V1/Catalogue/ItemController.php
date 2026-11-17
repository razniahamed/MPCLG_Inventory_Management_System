<?php

namespace App\Http\Controllers\Api\V1\Catalogue;

use App\Http\Controllers\Api\V1\Organisation\MasterDataController;
use App\Http\Requests\Catalogue\ItemRequest;
use App\Http\Resources\MasterRecordResource;
use App\Models\Item;
use App\Models\ItemUnitConversion;
use App\Models\StockLedger;
use App\Services\Catalogue\CatalogueService;
use App\Services\Organisation\MasterDataService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends MasterDataController
{
    protected string $model = Item::class;

    protected array $filters = [
        'category_id' => 'category_id',
        'tracking_method' => 'tracking_method',
        'is_paper' => 'is_paper',
    ];

    protected array $sorts = ['id', 'item_code', 'name_en', 'created_at'];

    protected array $search = ['item_code', 'name_en', 'legacy_code', 'brand'];

    protected array $with = ['category', 'baseUnit', 'purchaseUnit', 'issueUnit'];

    protected string $defaultSort = 'name_en';

    protected string $label = 'Item';

    public function __construct(MasterDataService $service, private readonly CatalogueService $catalogue)
    {
        parent::__construct($service);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Item $item): JsonResponse
    {
        $data = (new MasterRecordResource($item->load([...$this->with, 'conversions.fromUnit', 'conversions.toUnit'])))->resolve(request());
        $data['stock_by_store'] = $this->catalogue->stockByStore($item);

        return ApiResponse::success($data);
    }

    public function store(ItemRequest $request): JsonResponse
    {
        return $this->createRecord($request, fn (Item $i) => $this->catalogue->validateItem($i));
    }

    public function update(ItemRequest $request, Item $item): JsonResponse
    {
        return $this->updateRecord($request, $item, fn (Item $i) => $this->catalogue->validateItem($i));
    }

    public function destroy(Request $request, Item $item): JsonResponse
    {
        return $this->retireRecord($request, $item, [
            'stock records' => fn () => StockLedger::where('item_id', $item->id)->exists(),
        ]);
    }

    public function conversions(Item $item): JsonResponse
    {
        return ApiResponse::success(MasterRecordResource::collection($item->conversions()->with(['fromUnit', 'toUnit'])->get()));
    }

    public function addConversion(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'from_unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'to_unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'factor' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $conversion = $this->catalogue->addConversion($item, $data);

        return ApiResponse::created(new MasterRecordResource($conversion->load(['fromUnit', 'toUnit'])), 'Conversion recorded. A second officer must approve it before it is used.');
    }

    public function approveConversion(Request $request, Item $item, ItemUnitConversion $conversion): JsonResponse
    {
        abort_unless($conversion->item_id === $item->id, 404);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        return ApiResponse::success(new MasterRecordResource($this->catalogue->approveConversion($conversion, $reason)->load(['fromUnit', 'toUnit'])), 'Conversion approved.');
    }
}
