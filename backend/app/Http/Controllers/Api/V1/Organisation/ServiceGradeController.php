<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Requests\Organisation\ServiceGradeRequest;
use App\Models\Officer;
use App\Models\ServiceGrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceGradeController extends MasterDataController
{
    protected string $model = ServiceGrade::class;

    protected array $filters = ['public_service_id' => 'public_service_id'];

    protected array $sorts = ['id', 'code', 'rank', 'created_at'];

    protected array $search = ['code'];

    protected array $with = ['publicService'];

    protected string $defaultSort = 'rank';

    protected string $label = 'Service grade';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(ServiceGrade $serviceGrade): JsonResponse
    {
        return $this->showRecord($serviceGrade);
    }

    public function store(ServiceGradeRequest $request): JsonResponse
    {
        return $this->createRecord($request);
    }

    public function update(ServiceGradeRequest $request, ServiceGrade $serviceGrade): JsonResponse
    {
        return $this->updateRecord($request, $serviceGrade);
    }

    public function destroy(Request $request, ServiceGrade $serviceGrade): JsonResponse
    {
        return $this->retireRecord($request, $serviceGrade, [
            'officers' => fn () => Officer::where('service_grade_id', $serviceGrade->id)->exists(),
        ]);
    }
}
