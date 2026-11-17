<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Generic shape for simple organisation master records: every column (with
 * casts) except soft-delete internals, plus whichever relations were loaded.
 */
class MasterRecordResource extends JsonResource
{
    private const HIDDEN = ['deleted_at'];

    public function toArray(Request $request): array
    {
        $data = $this->resource->attributesToArray();

        foreach (self::HIDDEN as $key) {
            unset($data[$key]);
        }

        foreach ($this->resource->getRelations() as $name => $relation) {
            $data[Str::snake($name)] = $relation === null
                ? null
                : ($relation instanceof Collection
                    ? MasterRecordResource::collection($relation)->resolve($request)
                    : (new MasterRecordResource($relation))->resolve($request));
        }

        return $data;
    }
}
