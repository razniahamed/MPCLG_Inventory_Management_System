<?php

namespace App\Services\Approvals;

use App\Exceptions\ApiException;
use App\Models\Approval;
use App\Models\ApprovalDecision;
use App\Models\ApprovalFlow;
use App\Services\Access\Actor;
use App\Services\Access\PermissionResolver;
use App\Services\Access\Scope;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Configurable approval workflow (approval_flows / steps / approvals /
 * approval_decisions). Steps run in order; each needs its own permission,
 * nobody approves their own document, and every decision snapshots the
 * officer's designation and department (Section 7.6).
 */
class ApprovalService
{
    public const DECISIONS = ['approve', 'reduce', 'return_for_clarification', 'reject'];

    public function __construct(
        private readonly Actor $actor,
        private readonly PermissionResolver $resolver,
    ) {}

    /** Open approval rows for a document version (supersedes older versions). */
    public function start(Model $document, string $flowCode, int $version, mixed $amount = null): void
    {
        $type = $document->getTable();

        Approval::query()
            ->where(['approvable_type' => $type, 'approvable_id' => $document->getKey()])
            ->where('version_no', '<', $version)
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        $flow = ApprovalFlow::where('code', $flowCode)->with('steps')->firstOrFail();

        foreach ($flow->steps as $step) {
            if (! $step->is_required) {
                continue;
            }
            if ($amount !== null && $step->min_amount !== null && Dec::cmp($amount, $step->min_amount, 2) < 0) {
                continue;
            }

            Approval::create([
                'approvable_type' => $type,
                'approvable_id' => $document->getKey(),
                'version_no' => $version,
                'flow_step_id' => $step->id,
                'status' => 'pending',
            ]);
        }
    }

    public function current(Model $document, int $version): ?Approval
    {
        return Approval::query()
            ->where(['approvable_type' => $document->getTable(), 'approvable_id' => $document->getKey(), 'version_no' => $version, 'status' => 'pending'])
            ->join('approval_flow_steps', 'approval_flow_steps.id', '=', 'approvals.flow_step_id')
            ->orderBy('approval_flow_steps.step_no')
            ->select('approvals.*')
            ->with('step.permission')
            ->first();
    }

    public function isComplete(Model $document, int $version): bool
    {
        $rows = Approval::where(['approvable_type' => $document->getTable(), 'approvable_id' => $document->getKey(), 'version_no' => $version])->get();

        return $rows->isNotEmpty() && $rows->every(fn ($a) => in_array($a->status, ['approved', 'reduced'], true));
    }

    /**
     * Record a decision on the current step.
     *
     * @param  array{remarks?: ?string, scope?: ?Scope, owner_officer_id?: ?int, approved_qty?: ?array, authority_ref?: ?string, expect_permission?: ?string}  $opts
     */
    public function decide(Model $document, int $version, string $decision, array $opts = []): Approval
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ApiException::businessRule('Unknown decision.');
        }

        $approval = $this->current($document, $version);
        if (! $approval) {
            throw ApiException::businessRule('There is no approval step waiting on this document.');
        }

        $step = $approval->step;
        $code = $step->permission->code;

        if (! empty($opts['expect_permission']) && $opts['expect_permission'] !== $code) {
            throw ApiException::businessRule("This document is waiting for '{$step->name}' ({$code}), not '{$opts['expect_permission']}'.");
        }

        $user = $this->actor->user();
        $explain = $this->resolver->explain($user, $code, $opts['scope'] ?? null);
        if (! $explain['allowed']) {
            throw new ApiException("This step needs the '{$code}' permission".(($opts['scope'] ?? null) && ! $opts['scope']->isGlobal() ? ' for this '.$opts['scope']->type->value : '').'.', 'PERMISSION_DENIED', 403);
        }

        $officer = $this->actor->officer();
        if (! $step->allow_self_approval && isset($opts['owner_officer_id']) && $opts['owner_officer_id'] === $officer->id) {
            throw ApiException::businessRule('You cannot approve your own document (no self-approval).');
        }

        if ($decision === 'reduce' && ! $step->can_reduce_qty) {
            throw ApiException::businessRule("The '{$step->name}' step cannot reduce quantities.");
        }

        if (in_array($decision, ['reduce', 'return_for_clarification', 'reject'], true) && blank($opts['remarks'] ?? null)) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, ['remarks' => ['Remarks are mandatory when reducing, returning or rejecting.']]);
        }

        $delegationId = collect($explain['sources'])->firstWhere('source', 'delegation')['source_id'] ?? null;
        $onlyDelegated = collect($explain['sources'])->every(fn ($s) => $s['source'] === 'delegation');

        ApprovalDecision::create([
            'approval_id' => $approval->id,
            'decision' => $decision,
            'decided_by_officer_id' => $officer->id,
            'decided_by_user_id' => $user->id,
            'designation_snapshot' => $officer->designation?->name ?? 'Unknown',
            'department_snapshot' => $officer->department?->name_en,
            'acting_under_delegation_id' => $onlyDelegated ? $delegationId : null,
            'authority_ref' => $opts['authority_ref'] ?? null,
            'approved_qty_snapshot' => $opts['approved_qty'] ?? null,
            'remarks' => $opts['remarks'] ?? null,
            'decided_at' => now(),
        ]);

        $approval->update(['status' => match ($decision) {
            'approve' => 'approved',
            'reduce' => 'reduced',
            'return_for_clarification' => 'returned',
            'reject' => 'rejected',
        }]);

        if (in_array($decision, ['return_for_clarification', 'reject'], true)) {
            Approval::query()
                ->where(['approvable_type' => $document->getTable(), 'approvable_id' => $document->getKey(), 'version_no' => $version, 'status' => 'pending'])
                ->update(['status' => 'superseded']);
        }

        return $approval->fresh('step');
    }

    /** Timeline for API resources. */
    public function history(Model $document): Collection
    {
        return Approval::query()
            ->where(['approvable_type' => $document->getTable(), 'approvable_id' => $document->getKey()])
            ->with(['step.permission', 'decisions'])
            ->orderBy('version_no')
            ->orderBy('flow_step_id')
            ->get()
            ->map(fn (Approval $a) => [
                'version_no' => $a->version_no,
                'step_no' => $a->step->step_no,
                'step' => $a->step->name,
                'permission' => $a->step->permission->code,
                'status' => $a->status,
                'decisions' => $a->decisions->map(fn (ApprovalDecision $d) => [
                    'decision' => $d->decision,
                    'officer_id' => $d->decided_by_officer_id,
                    'designation' => $d->designation_snapshot,
                    'department' => $d->department_snapshot,
                    'under_delegation' => $d->acting_under_delegation_id !== null,
                    'remarks' => $d->remarks,
                    'decided_at' => $d->decided_at->toIso8601String(),
                ])->all(),
            ]);
    }
}
