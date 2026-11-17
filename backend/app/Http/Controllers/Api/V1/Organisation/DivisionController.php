<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Requests\Organisation\DivisionRequest;
use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DivisionController extends MasterDataController
{
    protected string $model = Division::class;

    protected array $sorts = ['id', 'code', 'name_en', 'sort_order', 'created_at'];

    protected array $search = ['code', 'name_en', 'name_si', 'name_ta'];

    protected array $with = ['headDesignation'];

    protected string $defaultSort = 'sort_order';

    protected string $label = 'Division';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Division $division): JsonResponse
    {
        return $this->showRecord($division);
    }

    public function store(DivisionRequest $request): JsonResponse
    {
        return $this->createRecord($request);
    }

    public function update(DivisionRequest $request, Division $division): JsonResponse
    {
        return $this->updateRecord($request, $division);
    }

    public function destroy(Request $request, Division $division): JsonResponse
    {
        return $this->retireRecord($request, $division, [
            'departments' => fn () => $division->departments()->exists(),
        ]);
    }
}
