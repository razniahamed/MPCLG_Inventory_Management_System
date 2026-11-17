<?php

namespace App\Models;

use App\Enums\PermissionEffect;
use App\Enums\ScopeType;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user permission override: turns ONE permission on (allow) or off (deny) for ONE user.
 */
class UserPermission extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effect' => PermissionEffect::class,
            'scope_type' => ScopeType::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /** Not revoked and inside its validity window right now. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $this->scopeOpen($query)->where('user_permissions.valid_from', '<=', now());
    }

    /** Not revoked and not yet expired (may start in the future). */
    public function scopeOpen(Builder $query): Builder
    {
        $now = now();

        return $query->whereNull('user_permissions.revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('user_permissions.valid_to')->orWhere('user_permissions.valid_to', '>', $now));
    }
}
