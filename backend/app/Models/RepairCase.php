<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairCase extends BaseModel
{
    use HasPublicId;

    protected $hidden = ['open_key'];

    protected function casts(): array
    {
        return [
            'under_warranty' => 'boolean',
            'quotation_amount' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'dispatched_at' => 'datetime',
            'returned_at' => 'datetime',
            'tested_at' => 'datetime',
            'expected_return_on' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'provider_supplier_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'reported_by_officer_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(RepairUpdate::class)->orderBy('event_at')->orderBy('id');
    }
}
