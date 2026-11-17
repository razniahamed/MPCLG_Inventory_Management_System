<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One row per (document version, step). */
class Approval extends BaseModel
{
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlowStep::class, 'flow_step_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class)->orderBy('id');
    }
}
