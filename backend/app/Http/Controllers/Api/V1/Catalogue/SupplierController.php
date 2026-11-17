<?php

namespace App\Http\Controllers\Api\V1\Catalogue;

use App\Http\Controllers\Api\V1\Organisation\MasterDataController;
use App\Http\Requests\Catalogue\SupplierRequest;
use App\Http\Resources\MasterRecordResource;
use App\Models\Grn;
use App\Models\Supplier;
use App\Services\Catalogue\CatalogueService;
use App\Services\Organisation\MasterDataService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends MasterDataController
{
    protected string $model = Supplier::class;

    protected array $filters = ['supplier_type' => 'supplier_type'];

    protected array $sorts = ['id', 'code', 'name', 'created_at'];

    protected array $search = ['code', 'name', 'contact_person', 'email', 'tax_ref'];

    protected array $with = ['categories'];

    protected string $defaultSort = 'name';

    protected string $label = 'Supplier';

    public function __construct(MasterDataService $service, private readonly CatalogueService $catalogue)
    {
        parent::__construct($service);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->showRecord($supplier);
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        return ApiResponse::created(new MasterRecordResource($this->catalogue->saveSupplier(null, $request->validated())), 'Supplier created.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($this->catalogue->saveSupplier($supplier, $request->validated())), 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        return $this->retireRecord($request, $supplier, [
            'open goods received notes' => fn () => Grn::where('supplier_id', $supplier->id)->whereNotIn('status', ['posted', 'rejected', 'reversed'])->exists(),
        ]);
    }
}
