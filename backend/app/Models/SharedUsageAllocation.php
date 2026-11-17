<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharedUsageAllocation extends BaseModel
{
    protected function casts(): array
    {
        return ['period_from_on' => 'date', 'period_to_on' => 'date', 'total_usage' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SharedUsageAllocationLine::class, 'allocation_id');
    }
}
