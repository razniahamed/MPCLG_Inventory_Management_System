<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Exceptions\ApiException;
use App\Http\Requests\Organisation\LocationRequest;
use App\Models\Location;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends MasterDataController
{
    protected string $model = Location::class;

    protected array $filters = [
        'site_id' => 'site_id',
        'store_id' => 'store_id',
        'parent_location_id' => 'parent_location_id',
        'location_type' => 'location_type',
    ];

    protected array $sorts = ['id', 'code', 'name', 'created_at'];

    protected array $search = ['code', 'name'];

    protected array $with = ['site', 'store', 'parent'];

    protected string $label = 'Location';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Location $location): JsonResponse
    {
        return $this->showRecord($location);
    }

    public function store(LocationRequest $request): JsonResponse
    {
        return $this->createRecord($request, fn (Location $l) => $this->assertConsistent($l));
    }

    public function update(LocationRequest $request, Location $location): JsonResponse
    {
        return $this->updateRecord($request, $location, fn (Location $l) => $this->assertConsistent($l));
    }

    public function destroy(Request $request, Location $location): JsonResponse
    {
        return $this->retireRecord($request, $location, [
            'child locations' => fn () => $location->children()->exists(),
        ]);
    }

    /** Store and parent location must belong to the same site; no cycles. */
    private function assertConsistent(Location $location): void
    {
        if ($location->store_id && Store::whereKey($location->store_id)->value('site_id') !== $location->site_id) {
            throw ApiException::businessRule('The store belongs to a different site.');
        }

        $parentId = $location->parent_location_id;
        $seen = [];
        while ($parentId !== null && ! isset($seen[$parentId])) {
            if ($location->exists && $parentId === $location->id) {
                throw ApiException::businessRule('A location cannot be placed inside itself.');
            }
            $seen[$parentId] = true;
            $parent = Location::whereKey($parentId)->first(['id', 'site_id', 'parent_location_id']);
            if ($parent && $parent->site_id !== $location->site_id) {
                throw ApiException::businessRule('The parent location belongs to a different site.');
            }
            $parentId = $parent?->parent_location_id;
        }
    }
}
