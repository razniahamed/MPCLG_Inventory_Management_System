<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentReturnLine extends BaseModel
{
    protected function casts(): array
    {
        return ['qty_returned' => 'decimal:3', 'qty_accepted' => 'decimal:3'];
    }

    public function issueLine(): BelongsTo
    {
        return $this->belongsTo(IssueLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
