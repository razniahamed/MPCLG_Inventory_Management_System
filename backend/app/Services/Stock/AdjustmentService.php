<?php

namespace App\Services\Stock;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\StockAdjustment;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Support\Dec;
use App\Support\DocumentNumber;
use App\Support\Idempotency;
use Illuminate\Support\Facades\DB;

/** Stock adjustments: the approver must differ from the recorder (rule 9). */
class AdjustmentService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly LedgerService $ledger,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data): StockAdjustment
    {
        $this->actor->authorize('adjustment.create', Scope::store((int) $data['store_id']));
        $officer = $this->actor->officer();

        if (Dec::isZero($data['qty_delta'])) {
            throw ApiException::businessRule('The adjustment quantity cannot be zero.');
        }

        return DB::transaction(function () use ($data, $officer) {
            $adjustment = StockAdjustment::create([
                'adjustment_no' => DocumentNumber::next('ADJ'),
                'item_id' => $data['item_id'],
                'store_id' => $data['store_id'],
                'batch_no' => $data['batch_no'] ?? null,
                'qty_delta' => $data['qty_delta'],
                'reason' => $data['reason'],
                'recorded_by_officer_id' => $officer->id,
                'status' => 'submitted',
            ]);
            $this->approvals->start($adjustment, 'STOCK_ADJUSTMENT', 1);
            $this->audit->log(AuditAction::Created, $adjustment, null, $adjustment->only(['adjustment_no', 'item_id', 'store_id', 'qty_delta']), $data['reason']);

            return $adjustment;
        });
    }

    public function approvePost(StockAdjustment $adjustment, string $key, ?string $remarks): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $key, $remarks) {
            $adjustment = StockAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if (Idempotency::alreadyPosted($adjustment, $key)) {
                return $adjustment;
            }
            if ($adjustment->status !== 'submitted') {
                throw ApiException::businessRule("Adjustment {$adjustment->adjustment_no} is {$adjustment->status}.");
            }

            $this->approvals->decide($adjustment, 1, 'approve', [
                'scope' => Scope::store($adjustment->store_id),
                'owner_officer_id' => $adjustment->recorded_by_officer_id,
                'remarks' => $remarks,
                'expect_permission' => 'adjustment.approve',
            ]);
            $officer = $this->actor->officer();

            $entry = $this->ledger->post([
                'item_id' => $adjustment->item_id,
                'store_id' => $adjustment->store_id,
                'batch_no' => $adjustment->batch_no,
                'qty' => $adjustment->qty_delta,
                'movement_type' => Dec::isPositive($adjustment->qty_delta) ? 'adjustment_plus' : 'adjustment_minus',
                'event_on' => now()->toDateString(),
                'source_type' => 'stock_adjustments',
                'source_id' => $adjustment->id,
                'document_no' => $adjustment->adjustment_no,
                'remarks' => $adjustment->reason,
            ], $officer);

            $adjustment->update([
                'status' => 'posted',
                'approved_by_officer_id' => $officer->id,
                'approved_at' => now(),
                'ledger_id' => $entry->id,
                'idempotency_key' => $key,
            ]);
            $this->audit->log(AuditAction::Posted, $adjustment, ['status' => 'submitted'], ['status' => 'posted'], $remarks);

            return $adjustment;
        });
    }

    public function reject(StockAdjustment $adjustment, string $reason): StockAdjustment
    {
        if ($adjustment->status !== 'submitted') {
            throw ApiException::businessRule("Adjustment {$adjustment->adjustment_no} is {$adjustment->status}.");
        }

        return DB::transaction(function () use ($adjustment, $reason) {
            $this->approvals->decide($adjustment, 1, 'reject', [
                'scope' => Scope::store($adjustment->store_id),
                'owner_officer_id' => $adjustment->recorded_by_officer_id,
                'remarks' => $reason,
            ]);
            $adjustment->update(['status' => 'rejected']);
            $this->audit->log(AuditAction::StatusChanged, $adjustment, null, ['status' => 'rejected'], $reason);

            return $adjustment;
        });
    }
}
