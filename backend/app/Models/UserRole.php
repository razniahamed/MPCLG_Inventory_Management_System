<?php

namespace App\Models;

use App\Enums\ScopeType;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'is_active' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Active, not revoked and inside its validity window right now. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $this->scopeOpen($query)->where('user_roles.valid_from', '<=', now());
    }

    /** Active and not revoked (may start in the future). */
    public function scopeOpen(Builder $query): Builder
    {
        $now = now();

        return $query->where('user_roles.is_active', true)
            ->whereNull('user_roles.revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('user_roles.valid_to')->orWhere('user_roles.valid_to', '>', $now));
    }
}
