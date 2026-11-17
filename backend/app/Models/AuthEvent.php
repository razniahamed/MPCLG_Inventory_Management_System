<?php

namespace App\Models;

use App\Enums\AuthEventType;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthEvent extends Model
{
    use AppendOnly, HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event' => AuthEventType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
