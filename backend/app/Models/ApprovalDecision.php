<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDecision extends Model
{
    use AppendOnly, HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_qty_snapshot' => 'array', 'decided_at' => 'datetime'];
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'decided_by_officer_id');
    }
}
