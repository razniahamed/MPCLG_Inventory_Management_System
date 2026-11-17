<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disposal extends BaseModel
{
    public const METHODS = ['auction', 'transfer_out', 'scrap', 'donation', 'write_off'];

    protected function casts(): array
    {
        return ['disposed_on' => 'date', 'approved_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'proposed_by_officer_id');
    }
}
