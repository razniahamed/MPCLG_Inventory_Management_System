<?php

namespace App\Services\Requests;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Asset;
use App\Models\Discrepancy;
use App\Models\GoodsRequest;
use App\Models\Issue;
use App\Models\IssueAcknowledgement;
use App\Models\IssueLine;
use App\Models\RequestLine;
use App\Models\Reservation;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Assets\CustodyService;
use App\Services\Audit\AuditLogger;
use App\Services\Stock\LedgerService;
use App\Services\Stock\UnitConverter;
use App\Support\Dec;
use App\Support\DocumentNumber;
use App\Support\Idempotency;
use Illuminate\Support\Facades\DB;

/**
 * Issues: saving ≠ posting. Stock decreases ONLY when the Storekeeper posts
 * (rule 1); Σ issued ≤ approved per line (rule 2); concurrent posts serialise
 * on row locks (rule 3); posting is idempotent (rule 4).
 */
class IssueService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly LedgerService $ledger,
        private readonly UnitConverter $units,
        private readonly RequestService $requests,
        private readonly CustodyService $custody,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data): Issue
    {
        $request = GoodsRequest::findOrFail($data['request_id']);
        if (! in_array($request->status, GoodsRequest::OPEN_FOR_ISSUE, true)) {
            throw ApiException::businessRule("Request {$request->request_no} is {$request->status}; only approved requests can be issued.");
        }
        $this->actor->authorize('issue.create', Scope::store($request->store_id));
        $officer = $this->actor->officer();

        return DB::transaction(function () use ($request, $data, $officer) {
            $issue = Issue::create([
                'issue_no' => DocumentNumber::next('ISS'),
                'request_id' => $request->id,
                'store_id' => $request->store_id,
                'issue_on' => $data['issue_on'] ?? now()->toDateString(),
                'issued_by_officer_id' => $officer->id,
                'status' => 'draft',
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($data['lines'] as $input) {
                $line = RequestLine::where(['request_id' => $request->id, 'version_no' => $request->current_version_no])->findOrFail($input['request_line_id']);
                if (Dec::cmp($input['qty'], $line->outstandingQty()) > 0) {
                    throw ApiException::businessRule("Line {$line->line_no}: only {$line->outstandingQty()} remains approved for issue.");
                }
                IssueLine::create([
                    'issue_id' => $issue->id,
                    'request_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'unit_id' => $line->unit_id,
                    'qty' => $input['qty'],
                    'batch_no' => $input['batch_no'] ?? null,
                    'asset_id' => $input['asset_id'] ?? null,
                ]);
            }

            $this->audit->log(AuditAction::Created, $issue, null, ['issue_no' => $issue->issue_no, 'request' => $request->request_no]);

            return $issue->load('lines');
        });
    }

    public function post(Issue $issue, string $key): Issue
    {
        return DB::transaction(function () use ($issue, $key) {
            $issue = Issue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if (Idempotency::alreadyPosted($issue, $key)) {
                return $issue;
            }
            if ($issue->status !== 'draft') {
                throw ApiException::businessRule("Issue {$issue->issue_no} is {$issue->status}.");
            }
            $this->actor->authorize('issue.post', Scope::store($issue->store_id));
            $officer = $this->actor->officer();

            $request = GoodsRequest::whereKey($issue->request_id)->lockForUpdate()->firstOrFail();
            if (! in_array($request->status, GoodsRequest::OPEN_FOR_ISSUE, true)) {
                throw ApiException::businessRule("Request {$request->request_no} is {$request->status}.");
            }

            foreach ($issue->lines()->with('item')->get() as $line) {
                // Lock the request line: concurrent issues serialise here.
                $requestLine = RequestLine::whereKey($line->request_line_id)->lockForUpdate()->firstOrFail();
                if ($requestLine->version_no !== $request->current_version_no) {
                    throw ApiException::businessRule('The request was revised after this issue was prepared. Prepare a new issue.');
                }

                $newIssued = Dec::add($requestLine->issued_qty, $line->qty);
                if (Dec::cmp($newIssued, $requestLine->approved_qty ?? 0) > 0) {
                    throw ApiException::businessRule("Line {$requestLine->line_no}: issuing {$line->qty} would exceed the approved {$requestLine->approved_qty} (already issued {$requestLine->issued_qty}). Any more needs a new approval.");
                }

                $baseQty = $this->units->toBase($line->item, $line->unit_id, $line->qty);
                $consume = $this->consumeReservation($requestLine, $baseQty);

                if ($line->item->isAsset()) {
                    $this->issueAsset($line, $request, $officer->id);
                }

                $this->ledger->post([
                    'item_id' => $line->item_id,
                    'store_id' => $issue->store_id,
                    'batch_no' => $line->batch_no,
                    'qty' => Dec::neg($baseQty),
                    'movement_type' => 'issue',
                    'event_on' => $issue->issue_on,
                    'source_type' => 'issues',
                    'source_id' => $issue->id,
                    'source_line_id' => $line->id,
                    'document_no' => $issue->issue_no,
                    'consume_reserved' => $consume,
                    'remarks' => "To {$request->department_name_snapshot} ({$request->request_no})",
                ], $officer);

                $requestLine->update(['issued_qty' => $newIssued]);
            }

            $issue->update(['status' => 'posted', 'posted_at' => now(), 'idempotency_key' => $key]);
            $this->requests->refreshIssueStatus($request);
            $this->audit->log(AuditAction::Posted, $issue, ['status' => 'draft'], ['status' => 'posted']);

            return $issue;
        });
    }

    public function reverse(Issue $issue, string $reason): Issue
    {
        if ($issue->status !== 'posted') {
            throw ApiException::businessRule('Only posted issues can be reversed.');
        }
        $this->actor->authorize('issue.reverse', Scope::store($issue->store_id));

        return DB::transaction(function () use ($issue, $reason) {
            $issue = Issue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            $officer = $this->actor->officer();
            $this->ledger->reverseSource('issues', $issue->id, $officer, 'Reversal: '.$reason, $issue->issue_no);

            foreach ($issue->lines as $line) {
                $requestLine = RequestLine::whereKey($line->request_line_id)->lockForUpdate()->firstOrFail();
                $requestLine->update(['issued_qty' => Dec::sub($requestLine->issued_qty, $line->qty)]);

                if ($line->asset_id) {
                    $asset = Asset::findOrFail($line->asset_id);
                    $this->custody->move($asset, [
                        'operational_status' => 'in_store',
                        'store_id' => $issue->store_id,
                        'responsible_department_id' => null,
                        'custodian_officer_id' => null,
                        'location_id' => null,
                    ], 'Issue reversed', 'issues', $issue->id);
                }
            }

            $issue->update(['status' => 'reversed']);
            $this->requests->refreshIssueStatus($issue->request);
            $this->audit->log(AuditAction::Reversed, $issue, ['status' => 'posted'], ['status' => 'reversed'], $reason);

            return $issue;
        });
    }

    /**
     * Department confirms receipt. Issue and acknowledgement are separate; a
     * shortfall opens a discrepancy for review (never silently adjusted).
     *
     * @param  array<int, array{issue_line_id: int, received_qty: mixed}>  $lines
     */
    public function acknowledge(Issue $issue, string $status, ?string $remarks, array $lines = []): Issue
    {
        if ($issue->status !== 'posted') {
            throw ApiException::businessRule('Only posted issues can be acknowledged.');
        }
        if ($issue->acknowledgement()->exists()) {
            throw ApiException::businessRule('This issue has already been acknowledged.');
        }
        $request = $issue->request;
        $this->actor->authorize('issue.acknowledge', Scope::department($request->department_id));
        $officer = $this->actor->officer();

        if ($status === 'discrepancy' && blank($remarks)) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, ['remarks' => ['Describe the discrepancy.']]);
        }

        return DB::transaction(function () use ($issue, $status, $remarks, $lines, $officer, $request) {
            IssueAcknowledgement::create([
                'issue_id' => $issue->id,
                'received_by_officer_id' => $officer->id,
                'received_at' => now(),
                'status' => $status,
                'remarks' => $remarks,
            ]);
            $issue->update(['received_by_officer_id' => $officer->id]);

            if ($status === 'discrepancy') {
                $received = collect($lines)->keyBy('issue_line_id');
                foreach ($issue->lines as $line) {
                    $actual = $received->has($line->id) ? $received[$line->id]['received_qty'] : null;
                    if ($actual === null || Dec::cmp($actual, $line->qty) !== 0) {
                        Discrepancy::create([
                            'discrepancy_type' => 'receipt_shortfall',
                            'source_type' => 'issue_lines',
                            'source_id' => $line->id,
                            'expected_qty' => $line->qty,
                            'actual_qty' => $actual,
                            'description' => "{$issue->issue_no}: {$remarks}",
                            'status' => 'open',
                        ]);
                    }
                }
            }

            $request->update(['receipt_status' => $status === 'confirmed' ? 'confirmed' : 'discrepancy']);
            $this->audit->log(AuditAction::StatusChanged, $issue, null, ['acknowledged' => $status], $remarks);

            return $issue;
        });
    }

    private function consumeReservation(RequestLine $line, string $baseQty): string
    {
        $remaining = $baseQty;
        $consumed = Dec::n(0);

        foreach (Reservation::where(['request_line_id' => $line->id, 'status' => 'active'])->lockForUpdate()->get() as $r) {
            if (! Dec::isPositive($remaining)) {
                break;
            }
            $take = Dec::min($r->qty, $remaining);
            $left = Dec::sub($r->qty, $take);
            $r->update(Dec::isZero($left) ? ['qty' => $r->qty, 'status' => 'consumed'] : ['qty' => $left]);
            $consumed = Dec::add($consumed, $take);
            $remaining = Dec::sub($remaining, $take);
        }

        return $consumed;
    }

    private function issueAsset(IssueLine $line, GoodsRequest $request, int $officerId): void
    {
        if (! $line->asset_id) {
            throw ApiException::businessRule("{$line->item->item_code} is an asset-tracked item: choose the asset (by asset number) for each unit issued.");
        }
        if (Dec::cmp($line->qty, 1) !== 0) {
            throw ApiException::businessRule('Asset-tracked items are issued one asset per line.');
        }

        $asset = Asset::whereKey($line->asset_id)->lockForUpdate()->firstOrFail();
        if ($asset->item_id !== $line->item_id || $asset->operational_status !== 'in_store' || $asset->store_id !== $request->store_id) {
            throw ApiException::businessRule("Asset {$asset->asset_no} is not available in this store.");
        }

        $this->custody->move($asset, [
            'operational_status' => 'in_use',
            'store_id' => null,
            'responsible_department_id' => $request->department_id,
            'custodian_officer_id' => $request->submitted_by_officer_id,
            'in_service_on' => $asset->in_service_on ?? now()->toDateString(),
        ], 'Issued', 'issues', $line->issue_id);
    }
}
