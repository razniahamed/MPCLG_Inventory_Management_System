<?php

namespace App\Http\Resources;

use App\Models\Officer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Officer */
class OfficerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'officer_no' => $this->officer_no,
            'full_name' => $this->full_name,
            'name_with_initials' => $this->name_with_initials,
            'designation_id' => $this->designation_id,
            'designation' => $this->whenLoaded('designation', fn () => $this->designation?->only(['id', 'code', 'name'])),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department?->only(['id', 'code', 'name_en'])),
            'public_service_id' => $this->public_service_id,
            'service_grade_id' => $this->service_grade_id,
            'work_phone' => $this->work_phone,
            'work_email' => $this->work_email,
            'employment_status' => $this->employment_status?->value,
            'joined_on' => $this->joined_on?->toDateString(),
            'left_on' => $this->left_on?->toDateString(),
            'is_active' => $this->is_active,
            'has_user_account' => $this->whenLoaded('user', fn () => $this->user !== null),
            'assignments' => OfficerAssignmentResource::collection($this->whenLoaded('assignments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
