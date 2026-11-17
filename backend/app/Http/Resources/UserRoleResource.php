<?php

namespace App\Http\Resources;

use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserRole */
class UserRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = now();

        return [
            'id' => $this->id,
            'role' => $this->whenLoaded('role', fn () => $this->role->only(['id', 'code', 'name'])),
            'scope_type' => $this->scope_type->value,
            'scope_id' => $this->scope_id,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_to' => $this->valid_to?->toIso8601String(),
            'is_current' => $this->is_active && $this->revoked_at === null
                && $this->valid_from->lte($now) && ($this->valid_to === null || $this->valid_to->gt($now)),
            'granted_by' => $this->granted_by,
            'granted_at' => $this->granted_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoke_reason' => $this->revoke_reason,
        ];
    }
}
