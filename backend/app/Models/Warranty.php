<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An extension is a NEW linked row; earlier coverage is preserved. */
class Warranty extends BaseModel
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'provider_supplier_id');
    }

    public function extendsWarranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class, 'extends_warranty_id');
    }

    public function coversDate(\DateTimeInterface $date): bool
    {
        return $this->status !== 'void' && $this->starts_on->lte($date) && $this->ends_on->gte($date);
    }
}
