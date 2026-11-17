<?php

namespace App\Http\Resources;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permission */
class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'module' => $this->whenLoaded('module', fn () => $this->module->code),
            'resource' => $this->resource,
            'action' => $this->action,
            'is_sensitive' => $this->is_sensitive,
            'supports_scope' => $this->supports_scope,
            'is_enabled' => $this->is_enabled,
        ];
    }
}
