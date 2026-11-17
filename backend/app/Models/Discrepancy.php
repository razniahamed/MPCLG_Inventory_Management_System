<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discrepancy extends BaseModel
{
    protected function casts(): array
    {
        return ['expected_qty' => 'decimal:3', 'actual_qty' => 'decimal:3', 'resolved_at' => 'datetime'];
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'resolved_by_officer_id');
    }
}
