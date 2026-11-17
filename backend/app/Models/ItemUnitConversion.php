<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 1 from_unit = factor × to_unit, approved per item (never assumed). */
class ItemUnitConversion extends BaseModel
{
    protected function casts(): array
    {
        return ['factor' => 'decimal:6', 'approved_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }
}
