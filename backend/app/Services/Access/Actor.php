<?php

namespace App\Services\Access;

use App\Exceptions\ApiException;
use App\Models\Officer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in user and the officer acting (Section 1.2 rule 3: every action
 * has a named officer), plus scope-aware permission checks for services.
 */
class Actor
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    public function user(): User
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            throw new ApiException('Unauthenticated.', 'UNAUTHENTICATED', 401);
        }

        return $user;
    }

    /** The officer behind the signed-in account. Business documents require one. */
    public function officer(): Officer
    {
        $officer = $this->user()->officer()->with(['designation', 'department'])->first();
        if (! $officer) {
            throw ApiException::businessRule('Your account is not linked to an officer. Ask a System Administrator to link it before recording transactions.');
        }

        return $officer;
    }

    public function officerId(): int
    {
        return $this->officer()->id;
    }

    /** Require a permission within a scope (store / department …). */
    public function authorize(string $code, ?Scope $scope = null): void
    {
        if (! $this->resolver->can($this->user(), $code, $scope)) {
            $where = $scope && ! $scope->isGlobal() ? " for this {$scope->type->value}" : '';
            throw new ApiException("You do not have the '{$code}' permission{$where}.", 'PERMISSION_DENIED', 403);
        }
    }

    public function can(string $code, ?Scope $scope = null): bool
    {
        return $this->resolver->can($this->user(), $code, $scope);
    }

    /**
     * Ids within a scope type the user holds the permission for, or null when
     * the user holds it globally (no restriction).
     *
     * @return array<int, int>|null
     */
    public function scopeIds(string $code, string $scopeType): ?array
    {
        $ids = [];
        foreach ($this->resolver->scopesFor($this->user(), $code) as $scope) {
            if ($scope->isGlobal()) {
                return null;
            }
            if ($scope->type->value === $scopeType) {
                $ids[] = $scope->id;
            }
        }

        return $ids;
    }

    /** No self-approval (Section 5.4 rule 1). */
    public function assertNotSelf(?int $officerId, string $what = 'your own document'): void
    {
        if ($officerId !== null && $officerId === $this->officerId()) {
            throw ApiException::businessRule("You cannot approve {$what}.");
        }
    }
}
