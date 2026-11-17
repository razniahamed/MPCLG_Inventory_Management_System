<?php

namespace App\Http\Controllers\Api\V1\Catalogue;

use App\Http\Controllers\Api\V1\Organisation\MasterDataController;
use App\Http\Requests\Catalogue\ItemCategoryRequest;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCategoryController extends MasterDataController
{
    protected string $model = ItemCategory::class;

    protected array $filters = ['parent_id' => 'parent_id', 'default_tracking_method' => 'default_tracking_method'];

    protected array $sorts = ['id', 'code', 'name', 'created_at'];

    protected array $search = ['code', 'name'];

    protected array $with = ['parent'];

    protected string $defaultSort = 'name';

    protected string $label = 'Category';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(ItemCategory $itemCategory): JsonResponse
    {
        return $this->showRecord($itemCategory);
    }

    public function store(ItemCategoryRequest $request): JsonResponse
    {
        return $this->createRecord($request);
    }

    public function update(ItemCategoryRequest $request, ItemCategory $itemCategory): JsonResponse
    {
        return $this->updateRecord($request, $itemCategory);
    }

    public function destroy(Request $request, ItemCategory $itemCategory): JsonResponse
    {
        return $this->retireRecord($request, $itemCategory, ['items' => fn () => $itemCategory->items()->exists()]);
    }
}
