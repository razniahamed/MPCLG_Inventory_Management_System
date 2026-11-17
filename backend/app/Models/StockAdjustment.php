<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends BaseModel
{
    use HasPublicId;

    protected function casts(): array
    {
        return ['qty_delta' => 'decimal:3', 'approved_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'recorded_by_officer_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'approved_by_officer_id');
    }

    public function countLine(): BelongsTo
    {
        return $this->belongsTo(StockCountLine::class);
    }
}
