<?php

namespace App\Services\Procurement;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnLine;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Audit\AuditLogger;
use App\Services\Stock\LedgerService;
use App\Services\Stock\UnitConverter;
use App\Support\Dec;
use App\Support\DocumentNumber;
use App\Support\Idempotency;
use Illuminate\Support\Facades\DB;

/** Goods sent back to a supplier from a posted GRN. */
class SupplierReturnService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly LedgerService $ledger,
        private readonly UnitConverter $units,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data): SupplierReturn
    {
        $grn = Grn::findOrFail($data['grn_id']);
        if ($grn->status !== 'posted') {
            throw ApiException::businessRule('Returns can only be made against a posted GRN.');
        }
        $this->actor->authorize('supplier_return.manage', Scope::store($grn->store_id));
        $this->actor->officer();

        return DB::transaction(function () use ($grn, $data) {
            $return = SupplierReturn::create([
                'return_no' => DocumentNumber::next('SRN'),
                'grn_id' => $grn->id,
                'supplier_id' => $grn->supplier_id ?? throw ApiException::businessRule('This GRN has no supplier.'),
                'store_id' => $grn->store_id,
                'return_on' => $data['return_on'] ?? now()->toDateString(),
                'reason' => $data['reason'],
                'replacement_or_claim_ref' => $data['replacement_or_claim_ref'] ?? null,
                'status' => 'draft',
            ]);

            foreach ($data['lines'] as $line) {
                $grnLine = GrnLine::where('grn_id', $grn->id)->findOrFail($line['grn_line_id']);
                $alreadyReturned = SupplierReturnLine::query()
                    ->where('grn_line_id', $grnLine->id)
                    ->whereHas('grnLine')
                    ->whereIn('supplier_return_id', SupplierReturn::where('status', '!=', 'closed')->select('id'))
                    ->sum('qty');
                $max = Dec::sub($grnLine->accepted_qty, $alreadyReturned);
                if (Dec::cmp($line['qty'], $max) > 0) {
                    throw ApiException::businessRule("Line {$grnLine->line_no}: at most {$max} can be returned.");
                }

                SupplierReturnLine::create([
                    'supplier_return_id' => $return->id,
                    'grn_line_id' => $grnLine->id,
                    'item_id' => $grnLine->item_id,
                    'qty' => $line['qty'],
                    'unit_id' => $grnLine->unit_id,
                    'batch_no' => $grnLine->batch_no,
                ]);
            }

            $this->audit->log(AuditAction::Created, $return, null, ['return_no' => $return->return_no], $data['reason']);

            return $return->load('lines');
        });
    }

    public function post(SupplierReturn $return, string $key): SupplierReturn
    {
        return DB::transaction(function () use ($return, $key) {
            $return = SupplierReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            if (Idempotency::alreadyPosted($return, $key)) {
                return $return;
            }
            $this->actor->authorize('supplier_return.manage', Scope::store($return->store_id));
            $officer = $this->actor->officer();

            foreach ($return->lines()->with('item')->get() as $line) {
                $this->ledger->post([
                    'item_id' => $line->item_id,
                    'store_id' => $return->store_id,
                    'batch_no' => $line->batch_no,
                    'qty' => Dec::neg($this->units->toBase($line->item, $line->unit_id, $line->qty)),
                    'movement_type' => 'supplier_return',
                    'event_on' => $return->return_on ?? now()->toDateString(),
                    'source_type' => 'supplier_returns',
                    'source_id' => $return->id,
                    'source_line_id' => $line->id,
                    'document_no' => $return->return_no,
                ], $officer);
            }

            $return->update([
                'status' => 'posted',
                'approved_by_officer_id' => $officer->id,
                'posted_at' => now(),
                'idempotency_key' => $key,
            ]);
            $this->audit->log(AuditAction::Posted, $return, null, ['status' => 'posted']);

            return $return;
        });
    }
}
