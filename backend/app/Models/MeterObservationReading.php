<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Unknown is never zero: is_unknown = 1 means reading_value is NULL. */
class MeterObservationReading extends BaseModel
{
    protected function casts(): array
    {
        return ['reading_value' => 'integer', 'is_unknown' => 'boolean'];
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(MachineCounter::class, 'counter_id');
    }

    public function observation(): BelongsTo
    {
        return $this->belongsTo(MeterObservation::class, 'observation_id');
    }
}
