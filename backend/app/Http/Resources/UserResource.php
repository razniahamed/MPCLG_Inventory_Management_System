<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->status->value,
            'is_locked' => $this->isLocked(),
            'locked_until' => $this->locked_until?->toIso8601String(),
            'must_change_password' => $this->must_change_password,
            'password_changed_at' => $this->password_changed_at?->toIso8601String(),
            'mfa_enabled' => $this->mfa_enabled,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'officer_id' => $this->officer_id,
            'officer' => $this->whenLoaded('officer', fn () => $this->officer ? [
                'id' => $this->officer->id,
                'officer_no' => $this->officer->officer_no,
                'full_name' => $this->officer->full_name,
                'designation' => $this->officer->relationLoaded('designation') ? $this->officer->designation?->name : null,
                'department' => $this->officer->relationLoaded('department') ? $this->officer->department?->name_en : null,
            ] : null),
            'roles' => UserRoleResource::collection($this->whenLoaded('roleAssignments')),
            'deactivated_at' => $this->deactivated_at?->toIso8601String(),
            'deactivation_reason' => $this->deactivation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
