<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use AppendOnly, HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class);
    }
}
