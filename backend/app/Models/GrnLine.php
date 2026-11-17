<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrnLine extends BaseModel
{
    protected function casts(): array
    {
        return [
            'delivered_qty' => 'decimal:3',
            'accepted_qty' => 'decimal:3',
            'rejected_qty' => 'decimal:3',
            'pending_qty' => 'decimal:3',
            'expiry_on' => 'date',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }
}
