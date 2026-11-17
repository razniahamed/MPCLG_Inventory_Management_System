<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A login account. Never deleted; disabled through status (Section 6).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasAuditColumns, HasFactory, HasPublicId, Notifiable;

    protected $guarded = ['id', 'public_id'];

    protected $hidden = ['password', 'mfa_secret'];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'failed_login_count' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'mfa_enabled' => 'boolean',
            'mfa_secret' => 'encrypted',
            'permission_version' => 'integer',
            'deactivated_at' => 'datetime',
        ];
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active && ! $this->isLocked();
    }

    /** Invalidate cached permission sets for this user. */
    public function bumpPermissionVersion(): void
    {
        $this->increment('permission_version');
    }

    // Sanctum/Laravel remember-token support is not used by the API.
    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
