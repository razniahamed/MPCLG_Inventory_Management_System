<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSchedule extends BaseModel
{
    protected function casts(): array
    {
        return ['last_service_on' => 'date', 'next_due_on' => 'date', 'is_active' => 'boolean'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function responsibleOfficer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'responsible_officer_id');
    }
}
