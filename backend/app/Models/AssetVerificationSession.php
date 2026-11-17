<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetVerificationSession extends BaseModel
{
    protected function casts(): array
    {
        return ['conducted_on' => 'date'];
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(AssetVerification::class, 'session_id');
    }

    public function leadOfficer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'lead_officer_id');
    }
}
