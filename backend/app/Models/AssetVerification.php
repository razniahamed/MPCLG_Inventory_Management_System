<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetVerification extends BaseModel
{
    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssetVerificationSession::class, 'session_id');
    }

    public function foundLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'found_location_id');
    }
}
