<?php

namespace App\Models;

use App\Enums\DelegationStatus;
use App\Enums\ScopeType;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegation extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DelegationStatus::class,
            'scope_type' => ScopeType::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_user_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', DelegationStatus::Active->value)
            ->where('valid_from', '<=', $now)
            ->where('valid_to', '>', $now);
    }
}
