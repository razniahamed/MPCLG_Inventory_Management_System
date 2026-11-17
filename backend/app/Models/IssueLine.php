<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueLine extends BaseModel
{
    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(RequestLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
