<?php

namespace App\Http\Resources;

use App\Models\OfficerAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OfficerAssignment */
class OfficerAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'officer_id' => $this->officer_id,
            'designation_id' => $this->designation_id,
            'designation' => $this->whenLoaded('designation', fn () => $this->designation?->only(['id', 'code', 'name'])),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department?->only(['id', 'code', 'name_en'])),
            'public_service_id' => $this->public_service_id,
            'service_grade_id' => $this->service_grade_id,
            'from_on' => $this->from_on?->toDateString(),
            'to_on' => $this->to_on?->toDateString(),
            'is_current' => $this->to_on === null,
            'authority_ref' => $this->authority_ref,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
