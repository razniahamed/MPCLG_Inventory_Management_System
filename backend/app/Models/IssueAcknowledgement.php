<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueAcknowledgement extends BaseModel
{
    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'received_by_officer_id');
    }
}
