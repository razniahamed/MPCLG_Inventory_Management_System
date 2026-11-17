<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use IsMasterData;

    /** Roles that must never carry approval / financial (is_sensitive) permissions (Section 5.4 rule 6). */
    public const NON_APPROVING_ROLES = ['system_admin', 'internal_auditor'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withPivot(['granted_by', 'granted_at']);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function isNonApproving(): bool
    {
        return in_array($this->code, self::NON_APPROVING_ROLES, true);
    }
}
