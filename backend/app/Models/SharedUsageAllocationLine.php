<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedUsageAllocationLine extends BaseModel
{
    protected function casts(): array
    {
        return ['allocated_usage' => 'integer'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
