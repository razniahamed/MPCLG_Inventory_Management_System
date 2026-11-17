<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairUpdate extends Model
{
    use AppendOnly, HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:2', 'event_at' => 'datetime'];
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'updated_by_officer_id');
    }
}
