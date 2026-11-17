<?php

namespace App\Models;

use App\Support\Dec;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cached balance per item / store / batch. available = usable − reserved. */
class StockBalance extends BaseModel
{
    protected function casts(): array
    {
        return [
            'on_hand_usable' => 'decimal:3',
            'on_hand_damaged' => 'decimal:3',
            'awaiting_inspection' => 'decimal:3',
            'reserved' => 'decimal:3',
            'expiry_on' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function available(): string
    {
        return Dec::sub($this->on_hand_usable, $this->reserved);
    }
}
