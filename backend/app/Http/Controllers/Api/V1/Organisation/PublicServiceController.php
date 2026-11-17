<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Requests\Organisation\PublicServiceRequest;
use App\Models\PublicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicServiceController extends MasterDataController
{
    protected string $model = PublicService::class;

    protected array $sorts = ['id', 'code', 'name', 'created_at'];

    protected array $search = ['code', 'name'];

    protected array $with = ['grades'];

    protected string $label = 'Public service';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(PublicService $publicService): JsonResponse
    {
        return $this->showRecord($publicService);
    }

    public function store(PublicServiceRequest $request): JsonResponse
    {
        return $this->createRecord($request);
    }

    public function update(PublicServiceRequest $request, PublicService $publicService): JsonResponse
    {
        return $this->updateRecord($request, $publicService);
    }

    public function destroy(Request $request, PublicService $publicService): JsonResponse
    {
        return $this->retireRecord($request, $publicService, [
            'service grades' => fn () => $publicService->grades()->exists(),
        ]);
    }
}
