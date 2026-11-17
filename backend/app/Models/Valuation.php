<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A new valuation is a new row; earlier values are never overwritten. */
class Valuation extends BaseModel
{
    protected function casts(): array
    {
        return [
            'previous_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'effective_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'assessed_by_officer_id');
    }
}
