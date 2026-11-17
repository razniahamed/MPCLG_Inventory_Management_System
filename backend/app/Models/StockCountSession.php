<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCountSession extends BaseModel
{
    use HasPublicId;

    protected function casts(): array
    {
        return ['cut_off_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'session_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'counted_by_officer_id');
    }
}
