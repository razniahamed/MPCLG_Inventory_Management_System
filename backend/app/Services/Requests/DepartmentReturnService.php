<?php

namespace App\Services\Requests;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Asset;
use App\Models\DepartmentReturn;
use App\Models\DepartmentReturnLine;
use App\Models\Issue;
use App\Models\IssueLine;
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

/** Goods returned by a department. Only the ACCEPTED quantity goes back to stock. */
class DepartmentReturnService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly LedgerService $ledger,
        private readonly UnitConverter $units,
        private readonly CustodyService $custody,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data): DepartmentReturn
    {
        $issue = Issue::with('request')->findOrFail($data['issue_id']);
        if ($issue->status !== 'posted') {
            throw ApiException::businessRule('Goods can only be returned from a posted issue.');
        }
        $this->actor->authorize('return.create', Scope::department($issue->request->department_id));
        $officer = $this->actor->officer();

        return DB::transaction(function () use ($issue, $data, $officer) {
            $return = DepartmentReturn::create([
                'return_no' => DocumentNumber::next('DRN'),
                'department_id' => $issue->request->department_id,
                'original_issue_id' => $issue->id,
                'store_id' => $issue->store_id,
                'return_on' => $data['return_on'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'status' => 'submitted',
                'returned_by_officer_id' => $officer->id,
            ]);

            foreach ($data['lines'] as $input) {
                $line = IssueLine::where('issue_id', $issue->id)->findOrFail($input['issue_line_id']);
                $already = DepartmentReturnLine::where('issue_line_id', $line->id)
                    ->whereHas('issueLine')
                    ->sum(DB::raw('COALESCE(qty_accepted, qty_returned)'));
                $max = Dec::sub($line->qty, $already);
                if (Dec::cmp($input['qty_returned'], $max) > 0) {
                    throw ApiException::businessRule("At most {$max} can be returned for this line.");
                }

                DepartmentReturnLine::create([
                    'department_return_id' => $return->id,
                    'issue_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'qty_returned' => $input['qty_returned'],
                    'condition' => $input['condition'] ?? null,
                    'remarks' => $input['remarks'] ?? null,
                ]);
            }

            $this->audit->log(AuditAction::Created, $return, null, ['return_no' => $return->return_no]);

            return $return->load('lines');
        });
    }

    /** @param  array<int, array{id: int, qty_accepted: mixed, condition?: ?string}>  $lines */
    public function inspect(DepartmentReturn $return, array $lines): DepartmentReturn
    {
        if ($return->status !== 'submitted') {
            throw ApiException::businessRule("Return {$return->return_no} is {$return->status}.");
        }
        $this->actor->authorize('return.inspect', Scope::store($return->store_id));
        $officer = $this->actor->officer();

        return DB::transaction(function () use ($return, $lines, $officer) {
            $byId = collect($lines)->keyBy('id');
            foreach ($return->lines as $line) {
                $accepted = Dec::n($byId[$line->id]['qty_accepted'] ?? 0);
                if (Dec::cmp($accepted, $line->qty_returned) > 0) {
                    throw ApiException::businessRule('Accepted quantity cannot exceed the quantity returned.');
                }
                $line->update(['qty_accepted' => $accepted, 'condition' => $byId[$line->id]['condition'] ?? $line->condition]);
            }
            $return->update(['status' => 'inspected', 'inspected_by_officer_id' => $officer->id]);

            return $return->load('lines');
        });
    }

    public function post(DepartmentReturn $return, string $key): DepartmentReturn
    {
        return DB::transaction(function () use ($return, $key) {
            $return = DepartmentReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if (Idempotency::alreadyPosted($return, $key)) {
                return $return;
            }
            if ($return->status !== 'inspected') {
                throw ApiException::businessRule('Inspect the returned goods before posting.');
            }
            $this->actor->authorize('return.inspect', Scope::store($return->store_id));
            $officer = $this->actor->officer();

            foreach ($return->lines()->with(['item', 'issueLine'])->get() as $line) {
                if (! Dec::isPositive($line->qty_accepted)) {
                    continue;
                }
                $this->ledger->post([
                    'item_id' => $line->item_id,
                    'store_id' => $return->store_id,
                    'batch_no' => $line->issueLine->batch_no,
                    'qty' => $this->units->toBase($line->item, $line->issueLine->unit_id, $line->qty_accepted),
                    'movement_type' => 'return_in',
                    'event_on' => $return->return_on ?? now()->toDateString(),
                    'source_type' => 'department_returns',
                    'source_id' => $return->id,
                    'source_line_id' => $line->id,
                    'document_no' => $return->return_no,
                ], $officer);

                if ($line->issueLine->asset_id) {
                    $this->custody->move(Asset::findOrFail($line->issueLine->asset_id), [
                        'operational_status' => 'in_store',
                        'store_id' => $return->store_id,
                        'responsible_department_id' => null,
                        'custodian_officer_id' => null,
                        'location_id' => null,
                        'condition' => in_array($line->condition, Asset::CONDITIONS, true) ? $line->condition : null,
                    ], 'Returned to stores', 'department_returns', $return->id);
                }
            }

            $return->update(['status' => 'posted', 'posted_at' => now(), 'idempotency_key' => $key]);
            $this->audit->log(AuditAction::Posted, $return, null, ['status' => 'posted']);

            return $return;
        });
    }
}
