<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\Asset;
use App\Services\Access\Actor;
use App\Services\Assets\AssetService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    use ScopesListings;

    private const WITH = ['item:id,item_code,name_en', 'category:id,code,name', 'responsibleDepartment:id,code,name_en', 'location:id,code,name', 'custodian:id,full_name', 'store:id,code,name'];

    public function __construct(private readonly AssetService $assets) {}

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()->with(self::WITH);

        // Staff with only asset.view see what they hold (their own custody).
        $actor = app(Actor::class);
        $wide = collect(['asset.create', 'asset.update', 'transfer.create', 'transfer.approve', 'verification.perform', 'repair.update', 'disposal.manage', 'report.assets', 'request.view_all'])
            ->contains(fn ($code) => $actor->can($code));
        if (! $wide) {
            $query->where('custodian_officer_id', $actor->user()->officer_id ?? 0);
        }

        $page = ListQuery::paginate($query, $request,
            filters: [
                'operational_status' => 'operational_status',
                'condition' => 'condition',
                'category_id' => 'category_id',
                'item_id' => 'item_id',
                'responsible_department_id' => 'responsible_department_id',
                'store_id' => 'store_id',
                'custodian_officer_id' => 'custodian_officer_id',
                'location_id' => 'location_id',
            ],
            sorts: ['id', 'asset_no', 'created_at', 'last_verified_at'],
            search: ['asset_no', 'serial_no', 'model', 'brand', 'legacy_code'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(Asset $asset): JsonResponse
    {
        $asset->load([...self::WITH, 'replaces:id,public_id,asset_no', 'custodyHistory', 'transfers.toDepartment:id,name_en', 'warranties.provider:id,name', 'repairs', 'valuations', 'serviceSchedules']);
        $data = (new MasterRecordResource($asset))->resolve(request());
        $data['qr'] = $this->assets->qr($asset);

        return ApiResponse::success($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:120'],
            'legacy_code' => ['nullable', 'string', 'max:40'],
            'source_type' => ['nullable', Rule::in(Asset::SOURCES)],
            'grn_line_id' => ['nullable', 'integer', Rule::exists('grn_lines', 'id')],
            'invoice_ref' => ['nullable', 'string', 'max:100'],
            'book_no' => ['nullable', 'string', 'max:30'],
            'page_no' => ['nullable', 'string', 'max:30'],
            'acquired_on' => ['nullable', 'date'],
            'acquired_on_quality' => ['nullable', Rule::in(['known', 'estimated', 'unknown'])],
            'condition' => ['nullable', Rule::in(Asset::CONDITIONS)],
            'store_id' => ['nullable', 'integer', Rule::exists('stores', 'id')],
            'responsible_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'custodian_officer_id' => ['nullable', 'integer', Rule::exists('officers', 'id')],
            'replaces_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $asset = $this->assets->create($data);

        return ApiResponse::created(new MasterRecordResource($asset->load(self::WITH)), "Asset {$asset->asset_no} registered.");
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $data = $request->validate([
            'serial_no' => ['sometimes', 'nullable', 'string', 'max:100'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'legacy_code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'invoice_ref' => ['sometimes', 'nullable', 'string', 'max:100'],
            'acquired_on' => ['sometimes', 'nullable', 'date'],
            'acquired_on_quality' => ['sometimes', Rule::in(['known', 'estimated', 'unknown'])],
            'condition' => ['sometimes', Rule::in(Asset::CONDITIONS)],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::success(new MasterRecordResource($this->assets->update($asset, $data)->load(self::WITH)), 'Asset updated.');
    }

    public function qr(Asset $asset): JsonResponse
    {
        return ApiResponse::success($this->assets->qr($asset));
    }

    /** QR landing: permitted basics only; login required (Section 16, decision 8). */
    public function scan(string $token): JsonResponse
    {
        return ApiResponse::success($this->assets->scan($token));
    }
}
