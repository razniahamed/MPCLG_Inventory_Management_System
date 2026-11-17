<?php

namespace App\Models;

use App\Support\Dec;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends BaseModel
{
    protected function casts(): array
    {
        return [
            'ordered_qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function grnLines(): HasMany
    {
        return $this->hasMany(GrnLine::class, 'po_line_id');
    }

    /** Σ accepted qty in POSTED GRNs (reversed GRNs excluded). */
    public function receivedQty(?int $exceptGrnId = null): string
    {
        $sum = GrnLine::query()
            ->where('po_line_id', $this->id)
            ->whereHas('grn', fn ($q) => $q->where('status', 'posted'))
            ->when($exceptGrnId, fn ($q) => $q->where('grn_id', '!=', $exceptGrnId))
            ->sum('accepted_qty');

        return Dec::n($sum);
    }

    public function outstandingQty(): string
    {
        return Dec::sub($this->ordered_qty, $this->receivedQty());
    }
}
