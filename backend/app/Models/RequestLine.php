<?php

namespace App\Models;

use App\Support\Dec;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestLine extends BaseModel
{
    protected function casts(): array
    {
        return [
            'requested_qty' => 'decimal:3',
            'approved_qty' => 'decimal:3',
            'issued_qty' => 'decimal:3',
            'version_no' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class, 'request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function outstandingQty(): string
    {
        return Dec::sub($this->approved_qty ?? 0, $this->issued_qty);
    }
}
