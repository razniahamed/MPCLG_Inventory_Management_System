<?php

namespace App\Services\Access;

use App\Enums\AuditAction;
use App\Enums\DelegationStatus;
use App\Exceptions\ApiException;
use App\Models\Delegation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A deputy acts for an approver for a stated period, scope and written
 * reference (Section 5.4 rule 5). Only approval (sensitive) permissions can be
 * delegated, and never to System Administrators or Internal Auditors.
 */
class DelegationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccessCache $cache,
        private readonly PermissionResolver $resolver,
        private readonly ScopeValidator $scopes,
    ) {}

    public function create(array $data): Delegation
    {
        /** @var User $delegator */
        $delegator = User::where('public_id', $data['delegator_user_id'])->firstOrFail();
        /** @var User $delegate */
        $delegate = User::where('public_id', $data['delegate_user_id'])->firstOrFail();

        if ($delegator->is($delegate)) {
            throw ApiException::businessRule('A user cannot delegate to themselves.');
        }

        foreach (['delegator' => $delegator, 'delegate' => $delegate] as $label => $user) {
            if (! $user->isActive()) {
                throw ApiException::businessRule("The {$label} account is not active.");
            }
        }

        if ($this->holdsNonApprovingRole($delegate)) {
            throw ApiException::businessRule('System Administrators and Internal Auditors cannot receive approval authority.');
        }

        $permission = null;
        if (! empty($data['permission_code'])) {
            $permission = Permission::where('code', $data['permission_code'])->firstOrFail();
            if (! $permission->is_sensitive) {
                throw ApiException::businessRule('Only approval / financial permissions can be delegated.');
            }
            if (! $this->resolver->explain($delegator, $permission->code, null, fresh: true)['allowed']) {
                throw ApiException::businessRule("The delegator does not hold '{$permission->code}'.");
            }
        }

        $scope = $this->scopes->resolve($data['scope']['scope_type'] ?? null, $data['scope']['scope_id'] ?? null);
        $validFrom = Carbon::parse($data['valid_from']);
        $validTo = Carbon::parse($data['valid_to']);

        return DB::transaction(function () use ($data, $delegator, $delegate, $permission, $scope, $validFrom, $validTo) {
            $delegation = Delegation::create([
                'delegator_user_id' => $delegator->id,
                'delegate_user_id' => $delegate->id,
                'permission_id' => $permission?->id,
                'scope_type' => $scope->type,
                'scope_id' => $scope->id,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'written_reference' => $data['written_reference'],
                'attachment_id' => $data['attachment_id'] ?? null,
                'status' => DelegationStatus::Active,
            ]);

            $this->cache->invalidateUser($delegate);
            $this->audit->log(AuditAction::Created, $delegation, null, [
                'delegator' => $delegator->username,
                'delegate' => $delegate->username,
                'permission' => $permission?->code ?? '*approval permissions*',
                'valid_from' => $validFrom->toDateTimeString(),
                'valid_to' => $validTo->toDateTimeString(),
                'written_reference' => $data['written_reference'],
            ] + $scope->toArray(), $data['reason'] ?? null);

            return $delegation;
        });
    }

    public function update(Delegation $delegation, array $data): Delegation
    {
        if ($delegation->status !== DelegationStatus::Active) {
            throw ApiException::businessRule('Only active delegations can be changed.');
        }

        return DB::transaction(function () use ($delegation, $data) {
            $original = $delegation->getOriginal();
            $delegation->fill(array_intersect_key($data, array_flip(['valid_to', 'written_reference'])));

            if ($delegation->valid_to->lte($delegation->valid_from)) {
                throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                    'valid_to' => ['valid_to must be after valid_from.'],
                ]);
            }

            $delegation->save();
            $this->cache->invalidateUser($delegation->delegate);
            $this->audit->logChanges($delegation, $original, $data['reason'] ?? null);

            return $delegation;
        });
    }

    public function revoke(Delegation $delegation, string $reason): Delegation
    {
        if ($delegation->status !== DelegationStatus::Active) {
            throw ApiException::businessRule('The delegation is not active.');
        }

        return DB::transaction(function () use ($delegation, $reason) {
            $delegation->forceFill([
                'status' => DelegationStatus::Revoked,
                'revoked_by' => Auth::id(),
                'revoked_at' => now(),
            ])->save();

            $this->cache->invalidateUser($delegation->delegate);
            $this->audit->log(AuditAction::StatusChanged, $delegation, ['status' => 'active'], ['status' => 'revoked'], $reason);

            return $delegation;
        });
    }

    /** Mark past-due delegations expired (scheduled daily). */
    public function expireDue(): int
    {
        $due = Delegation::query()
            ->where('status', DelegationStatus::Active->value)
            ->where('valid_to', '<=', now())
            ->get();

        foreach ($due as $delegation) {
            DB::transaction(function () use ($delegation) {
                $delegation->forceFill(['status' => DelegationStatus::Expired])->save();
                $this->cache->invalidateUser($delegation->delegate);
                $this->audit->log(AuditAction::StatusChanged, $delegation, ['status' => 'active'], ['status' => 'expired'], 'Validity period ended.');
            });
        }

        return $due->count();
    }

    private function holdsNonApprovingRole(User $user): bool
    {
        return UserRole::query()->open()
            ->where('user_id', $user->id)
            ->whereIn('role_id', Role::whereIn('code', Role::NON_APPROVING_ROLES)->pluck('id'))
            ->exists();
    }
}
