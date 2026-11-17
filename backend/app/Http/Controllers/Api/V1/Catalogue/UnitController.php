<?php

namespace App\Http\Controllers\Api\V1\Catalogue;

use App\Http\Controllers\Api\V1\Organisation\MasterDataController;
use App\Http\Requests\Catalogue\UnitRequest;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends MasterDataController
{
    protected string $model = Unit::class;

    protected array $filters = ['kind' => 'kind'];

    protected array $sorts = ['id', 'code', 'name'];

    protected array $search = ['code', 'name'];

    protected string $defaultSort = 'name';

    protected string $label = 'Unit';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Unit $unit): JsonResponse
    {
        return $this->showRecord($unit);
    }

    public function store(UnitRequest $request): JsonResponse
    {
        return $this->createRecord($request);
    }

    public function update(UnitRequest $request, Unit $unit): JsonResponse
    {
        return $this->updateRecord($request, $unit);
    }

    public function destroy(Request $request, Unit $unit): JsonResponse
    {
        return $this->retireRecord($request, $unit, [
            'items' => fn () => Item::where('base_unit_id', $unit->id)->orWhere('purchase_unit_id', $unit->id)->orWhere('issue_unit_id', $unit->id)->exists(),
        ]);
    }
}
