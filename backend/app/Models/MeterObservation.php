<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterObservation extends BaseModel
{
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'verified_at' => 'datetime',
            'is_baseline' => 'boolean',
            'flag_reasons' => 'array',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterObservationReading::class, 'observation_id');
    }

    public function observedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'observed_by_officer_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(MeterObservation::class, 'previous_observation_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'photo_attachment_id');
    }
}
