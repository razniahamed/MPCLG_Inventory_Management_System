<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnLine extends BaseModel
{
    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function grnLine(): BelongsTo
    {
        return $this->belongsTo(GrnLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
