<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Requests\Organisation\StoreOfficersRequest;
use App\Http\Requests\Organisation\StoreRequest;
use App\Http\Resources\MasterRecordResource;
use App\Models\Store;
use App\Services\Organisation\StoreService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends MasterDataController
{
    protected string $model = Store::class;

    protected array $filters = ['site_id' => 'site_id', 'store_type' => 'store_type'];

    protected array $sorts = ['id', 'code', 'name', 'created_at'];

    protected array $search = ['code', 'name'];

    protected array $with = ['site', 'responsibleOfficer'];

    protected string $label = 'Store';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Store $store): JsonResponse
    {
        return $this->showRecord($store);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->createRecord($request);
    }

    public function update(StoreRequest $request, Store $store): JsonResponse
    {
        return $this->updateRecord($request, $store);
    }

    public function destroy(Request $request, Store $store): JsonResponse
    {
        return $this->retireRecord($request, $store, [
            'locations' => fn () => $store->locations()->exists(),
        ]);
    }

    /** Officers (Storekeepers etc.) who work in the store. */
    public function officers(Store $store): JsonResponse
    {
        return ApiResponse::success(MasterRecordResource::collection($store->storeOfficers()->with('officer')->get()));
    }

    public function syncOfficers(StoreOfficersRequest $request, Store $store, StoreService $stores): JsonResponse
    {
        $store = $stores->syncOfficers($store, $request->validated('officers'), $request->validated('reason'));

        return ApiResponse::success(MasterRecordResource::collection($store->storeOfficers), 'Store officers updated.');
    }
}
