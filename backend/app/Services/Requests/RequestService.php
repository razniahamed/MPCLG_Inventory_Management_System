<?php

namespace App\Services\Requests;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\A4Exception;
use App\Models\Department;
use App\Models\GoodsRequest;
use App\Models\Item;
use App\Models\MeterObservation;
use App\Models\RequestLine;
use App\Models\Reservation;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Stock\LedgerService;
use App\Services\Stock\UnitConverter;
use App\Support\Dec;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Department requests: draft → submitted → under review (certified) →
 * approved (stock reserved) → partly / fully issued. Submitting does NOT
 * reduce stock (rule 1). Changing a returned request creates a new version and
 * restarts approval (rule 10).
 */
class RequestService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly ApprovalService $approvals,
        private readonly LedgerService $ledger,
        private readonly UnitConverter $units,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data): GoodsRequest
    {
        $this->actor->authorize('request.create', Scope::department((int) $data['department_id']));
        $officer = $this->actor->officer();
        $department = Department::findOrFail($data['department_id']);

        return DB::transaction(function () use ($data, $officer, $department) {
            $request = GoodsRequest::create([
                'request_no' => DocumentNumber::next(($data['request_type'] ?? 'goods') === 'a4_paper' ? 'A4R' : 'REQ'),
                'request_type' => $data['request_type'] ?? 'goods',
                'department_id' => $department->id,
                'department_name_snapshot' => $department->name_en,
                'store_id' => $data['store_id'],
                'period_from_on' => $data['period_from_on'] ?? null,
                'period_to_on' => $data['period_to_on'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'required_on' => $data['required_on'] ?? null,
                'staffing_count' => $data['staffing_count'] ?? null,
                'submitted_by_officer_id' => $officer->id,
                'submitted_designation_snapshot' => $officer->designation?->name,
                'paper_statement_id' => $data['paper_statement_id'] ?? null,
                'status' => 'draft',
                'current_version_no' => 1,
            ]);

            $this->writeLines($request, 1, $data['lines']);
            $this->syncMeterLinks($request, $data['observation_ids'] ?? null);
            $this->audit->log(AuditAction::Created, $request, null, ['request_no' => $request->request_no, 'type' => $request->request_type]);

            return $request;
        });
    }

    /** Edit a draft, or revise a returned request as a NEW version. */
    public function update(GoodsRequest $request, array $data): GoodsRequest
    {
        if (! in_array($request->status, ['draft', 'returned'], true)) {
            throw ApiException::businessRule('Only draft or returned requests can be changed.');
        }
        $this->actor->authorize('request.create', Scope::department($request->department_id));

        return DB::transaction(function () use ($request, $data) {
            $request->fill(array_intersect_key($data, array_flip(['purpose', 'required_on', 'staffing_count', 'period_from_on', 'period_to_on', 'paper_statement_id', 'store_id'])));

            if (isset($data['lines'])) {
                if ($request->status === 'returned') {
                    $request->current_version_no++;
                } else {
                    RequestLine::where(['request_id' => $request->id, 'version_no' => $request->current_version_no])->delete();
                }
                $this->writeLines($request, $request->current_version_no, $data['lines']);
            }

            $request->save();
            $this->syncMeterLinks($request, $data['observation_ids'] ?? null);
            $this->audit->log(AuditAction::Updated, $request, null, ['version_no' => $request->current_version_no], $data['reason'] ?? null);

            return $request;
        });
    }

    public function submit(GoodsRequest $request): GoodsRequest
    {
        if (! in_array($request->status, ['draft', 'returned'], true)) {
            throw ApiException::businessRule("Request {$request->request_no} is {$request->status}.");
        }
        $this->actor->authorize('request.submit', Scope::department($request->department_id));

        if ($request->currentLines()->count() === 0) {
            throw ApiException::businessRule('Add at least one line before submitting.');
        }
        if ($request->request_type === 'a4_paper') {
            $this->assertA4Evidence($request);
        }

        return DB::transaction(function () use ($request) {
            $request->update(['status' => 'submitted', 'submitted_at' => now()]);
            $this->approvals->start($request, $request->request_type === 'a4_paper' ? 'A4_REQUEST' : 'GOODS_REQUEST', $request->current_version_no);
            $this->audit->log(AuditAction::StatusChanged, $request, null, ['status' => 'submitted', 'version_no' => $request->current_version_no]);

            return $request;
        });
    }

    public function certify(GoodsRequest $request, ?string $remarks): GoodsRequest
    {
        $this->assertStatus($request, ['submitted']);

        return DB::transaction(function () use ($request, $remarks) {
            $this->approvals->decide($request, $request->current_version_no, 'approve', [
                'scope' => Scope::department($request->department_id),
                'owner_officer_id' => $request->submitted_by_officer_id,
                'remarks' => $remarks,
                'expect_permission' => 'request.certify',
            ]);
            $request->update(['status' => 'under_review', 'certified_by_officer_id' => $this->actor->officerId()]);
            $this->audit->log(AuditAction::Approved, $request, null, ['step' => 'certified'], $remarks);

            return $request;
        });
    }

    /**
     * Final approval; quantities may be reduced (remarks mandatory). Approval
     * reserves available stock for the approved quantities.
     *
     * @param  array<int, array{id: int, approved_qty: mixed}>  $lines
     */
    public function approve(GoodsRequest $request, array $lines, ?string $remarks): GoodsRequest
    {
        $this->assertStatus($request, ['under_review']);

        return DB::transaction(function () use ($request, $lines, $remarks) {
            $byId = collect($lines)->keyBy('id');
            $current = $request->currentLines()->lockForUpdate()->get();
            $reduced = false;
            $snapshot = [];

            foreach ($current as $line) {
                $approved = $byId->has($line->id) ? Dec::n($byId[$line->id]['approved_qty']) : Dec::n($line->requested_qty);
                if (Dec::cmp($approved, 0) < 0 || Dec::cmp($approved, $line->requested_qty) > 0) {
                    throw ApiException::businessRule("Line {$line->line_no}: approved quantity must be between 0 and the requested {$line->requested_qty}.");
                }
                $reduced = $reduced || Dec::cmp($approved, $line->requested_qty) < 0;
                $snapshot[$line->line_no] = $approved;
                $line->approved_qty = $approved;
            }

            $this->approvals->decide($request, $request->current_version_no, $reduced ? 'reduce' : 'approve', [
                'scope' => Scope::department($request->department_id),
                'owner_officer_id' => $request->submitted_by_officer_id,
                'remarks' => $remarks,
                'approved_qty' => $snapshot,
                'expect_permission' => 'request.approve',
            ]);

            foreach ($current as $line) {
                $line->save();
                $this->reserve($request, $line);
            }

            $request->update(['status' => 'approved']);
            $this->audit->log(AuditAction::Approved, $request, null, ['approved_qty' => $snapshot], $remarks);

            return $request;
        });
    }

    public function returnForClarification(GoodsRequest $request, string $remarks): GoodsRequest
    {
        $this->assertStatus($request, ['submitted', 'under_review']);

        return DB::transaction(function () use ($request, $remarks) {
            $this->approvals->decide($request, $request->current_version_no, 'return_for_clarification', [
                'scope' => Scope::department($request->department_id),
                'owner_officer_id' => $request->submitted_by_officer_id,
                'remarks' => $remarks,
            ]);
            $request->update(['status' => 'returned']);
            $this->audit->log(AuditAction::StatusChanged, $request, null, ['status' => 'returned'], $remarks);

            return $request;
        });
    }

    public function reject(GoodsRequest $request, string $remarks): GoodsRequest
    {
        $this->assertStatus($request, ['submitted', 'under_review']);

        return DB::transaction(function () use ($request, $remarks) {
            $this->approvals->decide($request, $request->current_version_no, 'reject', [
                'scope' => Scope::department($request->department_id),
                'owner_officer_id' => $request->submitted_by_officer_id,
                'remarks' => $remarks,
            ]);
            $request->update(['status' => 'rejected']);
            $this->audit->log(AuditAction::StatusChanged, $request, null, ['status' => 'rejected'], $remarks);

            return $request;
        });
    }

    public function cancel(GoodsRequest $request, string $reason): GoodsRequest
    {
        $this->assertStatus($request, ['draft', 'submitted', 'under_review', 'returned', 'approved']);
        $this->actor->authorize('request.cancel', Scope::department($request->department_id));
        if ($request->issues()->where('status', 'posted')->exists()) {
            throw ApiException::businessRule('Goods have already been issued against this request.');
        }

        return DB::transaction(function () use ($request, $reason) {
            $this->releaseAll($request, 'Request cancelled: '.$reason);
            $request->update(['status' => 'cancelled']);
            $this->audit->log(AuditAction::StatusChanged, $request, null, ['status' => 'cancelled'], $reason);

            return $request;
        });
    }

    /** Recalculate partly / fully issued after an issue is posted or reversed. */
    public function refreshIssueStatus(GoodsRequest $request): void
    {
        $lines = $request->currentLines()->get();
        $issuedAny = $lines->contains(fn ($l) => Dec::isPositive($l->issued_qty));
        $complete = $lines->every(fn ($l) => Dec::cmp($l->issued_qty, $l->approved_qty ?? 0) >= 0);

        $status = $complete ? 'fully_issued' : ($issuedAny ? 'partly_issued' : 'approved');
        $request->update(['status' => $status]);

        if ($complete) {
            $this->releaseAll($request, 'Request fully issued');
        }
    }

    public function releaseAll(GoodsRequest $request, string $reason): void
    {
        $lineIds = $request->allLines()->pluck('id');
        foreach (Reservation::whereIn('request_line_id', $lineIds)->where('status', 'active')->lockForUpdate()->get() as $r) {
            $this->ledger->release($r->item_id, $r->store_id, $r->qty, $r->batch_no);
            $r->update(['status' => 'released', 'released_reason' => $reason, 'released_at' => now()]);
        }
    }

    private function reserve(GoodsRequest $request, RequestLine $line): void
    {
        $item = $line->item ?? Item::find($line->item_id);
        $baseQty = $this->units->toBase($item, $line->unit_id, $line->approved_qty);
        $available = $this->ledger->available($line->item_id, $request->store_id);
        $qty = Dec::min($baseQty, Dec::cmp($available, 0) > 0 ? $available : 0);

        if (Dec::isPositive($qty)) {
            $this->ledger->reserve($line->item_id, $request->store_id, $qty);
            Reservation::create([
                'request_line_id' => $line->id,
                'item_id' => $line->item_id,
                'store_id' => $request->store_id,
                'qty' => $qty,
                'status' => 'active',
            ]);
        }
    }

    private function writeLines(GoodsRequest $request, int $version, array $lines): void
    {
        foreach (array_values($lines) as $i => $line) {
            $item = Item::findOrFail($line['item_id']);
            $unitId = (int) ($line['unit_id'] ?? $item->issue_unit_id);
            if (! in_array($unitId, $this->units->allowedUnitIds($item), true)) {
                throw ApiException::businessRule('Line '.($i + 1).": {$item->item_code} cannot be requested in that unit.");
            }
            if ($request->request_type === 'a4_paper' && ! $item->is_paper) {
                throw ApiException::businessRule('Line '.($i + 1).": {$item->item_code} is not a paper item.");
            }

            RequestLine::create([
                'request_id' => $request->id,
                'version_no' => $version,
                'line_no' => $i + 1,
                'item_id' => $item->id,
                'unit_id' => $unitId,
                'requested_qty' => $line['requested_qty'],
                'remarks' => $line['remarks'] ?? null,
            ]);
        }
    }

    private function syncMeterLinks(GoodsRequest $request, ?array $observationIds): void
    {
        if ($observationIds === null) {
            return;
        }

        $valid = MeterObservation::whereIn('id', $observationIds)->pluck('id')->all();
        $request->meterObservations()->sync($valid);
    }

    /** A4: paper statement + meter evidence, or an approved exception (rule 16). */
    private function assertA4Evidence(GoodsRequest $request): void
    {
        $missing = [];

        if (! $request->paper_statement_id) {
            $missing[] = 'a paper balance statement';
        }

        $observations = $request->meterObservations()->get();
        $usable = $observations->filter(fn ($o) => in_array($o->status, ['submitted', 'accepted', 'corrected'], true));
        $hasException = A4Exception::where(['request_id' => $request->id, 'status' => 'approved'])->exists();

        if ($usable->isEmpty() && ! $hasException) {
            $missing[] = 'meter readings (or an approved A4 exception)';
        }

        if ($observations->contains(fn ($o) => $o->status === 'flagged') && ! $hasException) {
            $missing[] = 'resolution of flagged meter readings';
        }

        if ($missing !== []) {
            throw ApiException::businessRule('A4 request is missing evidence: '.implode('; ', $missing).'.');
        }
    }

    private function assertStatus(GoodsRequest $request, array $allowed): void
    {
        if (! in_array($request->status, $allowed, true)) {
            throw ApiException::businessRule("Request {$request->request_no} is {$request->status}; this action needs: ".implode(' / ', $allowed).'.');
        }
    }
}
