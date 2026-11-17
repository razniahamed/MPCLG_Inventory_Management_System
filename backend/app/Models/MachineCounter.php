<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineCounter extends BaseModel
{
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
