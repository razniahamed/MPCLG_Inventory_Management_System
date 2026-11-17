<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Requests\Organisation\DesignationRequest;
use App\Models\Designation;
use App\Models\Officer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends MasterDataController
{
    protected string $model = Designation::class;

    protected array $filters = ['category' => 'category', 'is_head_post' => 'is_head_post'];

    protected array $sorts = ['id', 'code', 'name', 'created_at'];

    protected array $search = ['code', 'name'];

    protected string $label = 'Designation';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Designation $designation): JsonResponse
    {
        return $this->showRecord($designation);
    }

    public function store(DesignationRequest $request): JsonResponse
    {
        return $this->createRecord($request);
    }

    public function update(DesignationRequest $request, Designation $designation): JsonResponse
    {
        return $this->updateRecord($request, $designation);
    }

    public function destroy(Request $request, Designation $designation): JsonResponse
    {
        return $this->retireRecord($request, $designation, [
            'officers' => fn () => Officer::where('designation_id', $designation->id)->exists(),
        ]);
    }
}
