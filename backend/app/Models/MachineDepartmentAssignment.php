<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineDepartmentAssignment extends BaseModel
{
    protected function casts(): array
    {
        return ['from_on' => 'date', 'to_on' => 'date'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
