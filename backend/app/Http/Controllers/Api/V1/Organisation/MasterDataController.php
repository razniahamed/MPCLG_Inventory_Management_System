<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiRequest;
use App\Http\Resources\MasterRecordResource;
use App\Services\Organisation\MasterDataService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared list / show / create / update / retire behaviour for organisation
 * master data. Subclasses declare the model and query options.
 */
abstract class MasterDataController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** @var array<string, string|\Closure> */
    protected array $filters = [];

    /** @var array<int, string> */
    protected array $sorts = ['id', 'code', 'created_at'];

    /** @var array<int, string> */
    protected array $search = ['code'];

    /** @var array<int, string> */
    protected array $with = [];

    protected string $defaultSort = 'code';

    protected string $label = 'Record';

    public function __construct(protected readonly MasterDataService $service) {}

    protected function listing(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(
            $this->model::query()->with($this->with),
            $request,
            filters: ['is_active' => 'is_active'] + $this->filters,
            sorts: $this->sorts,
            search: $this->search,
            defaultSort: $this->defaultSort,
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    protected function showRecord(Model $model): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($model->load($this->with)));
    }

    protected function createRecord(ApiRequest $request, ?callable $validate = null): JsonResponse
    {
        $model = $this->service->create($this->model, $request->validated(), $validate);

        return ApiResponse::created(new MasterRecordResource($model->load($this->with)), "{$this->label} created.");
    }

    protected function updateRecord(ApiRequest $request, Model $model, ?callable $validate = null): JsonResponse
    {
        $model = $this->service->update($model, $request->validated(), $validate);

        return ApiResponse::success(new MasterRecordResource($model->load($this->with)), "{$this->label} updated.");
    }

    /** @param  array<string, \Closure(): bool>  $dependants */
    protected function retireRecord(Request $request, Model $model, array $dependants = []): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];
        $this->service->retire($model, $reason, $dependants);

        return ApiResponse::success(null, "{$this->label} retired.");
    }
}
