<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Goods Received Note. */
class Grn extends BaseModel
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'receipt_on' => 'date',
            'posting_on' => 'date',
            'invoice_on' => 'date',
            'posted_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'other_charges_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrnLine::class)->orderBy('line_no');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'received_by_officer_id');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'inspected_by_officer_id');
    }

    public function authorisedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'authorised_by_officer_id');
    }
}
