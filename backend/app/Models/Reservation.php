<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends BaseModel
{
    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'released_at' => 'datetime'];
    }

    public function requestLine(): BelongsTo
    {
        return $this->belongsTo(RequestLine::class);
    }
}
