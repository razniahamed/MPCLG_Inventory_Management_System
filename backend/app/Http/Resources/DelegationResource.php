<?php

namespace App\Http\Resources;

use App\Models\Delegation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Delegation */
class DelegationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delegator' => $this->whenLoaded('delegator', fn () => $this->delegator->only(['public_id', 'username'])),
            'delegate' => $this->whenLoaded('delegate', fn () => $this->delegate->only(['public_id', 'username'])),
            'permission' => $this->whenLoaded('permission', fn () => $this->permission?->code),
            'covers' => $this->permission_id ? 'single_permission' : 'all_approval_permissions',
            'scope_type' => $this->scope_type->value,
            'scope_id' => $this->scope_id,
            'valid_from' => $this->valid_from->toIso8601String(),
            'valid_to' => $this->valid_to->toIso8601String(),
            'written_reference' => $this->written_reference,
            'attachment_id' => $this->attachment_id,
            'status' => $this->status->value,
            'revoked_by' => $this->revoked_by,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
