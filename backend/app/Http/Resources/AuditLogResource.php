<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_at' => $this->event_at->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['public_id', 'username'])),
            'officer_id' => $this->officer_id,
            'action' => $this->action->value,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'reason' => $this->reason,
            'ip_address' => $this->ip_address,
            'request_id' => $this->request_id,
            'row_hash' => $this->row_hash,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
