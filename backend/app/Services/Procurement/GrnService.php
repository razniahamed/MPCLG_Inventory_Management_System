<?php

namespace App\Services\Procurement;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Stock\LedgerService;
use App\Services\Stock\UnitConverter;
use App\Support\Dec;
use App\Support\DocumentNumber;
use App\Support\Idempotency;
use Illuminate\Support\Facades\DB;

/**
 * Goods received notes: draft → awaiting inspection → awaiting approval →
 * posted (→ reversed). Only ACCEPTED quantity becomes usable stock; posting
 * is idempotent; the approver must differ from the receiving officer.
 */
class GrnService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly LedgerService $ledger,
        private readonly UnitConverter $units,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $audit,
        private readonly PurchaseOrderService $purchaseOrders,
    ) {}

    public function create(array $data): Grn
    {
        $this->actor->authorize('grn.create', Scope::store((int) $data['store_id']));
        $officer = $this->actor->officer();

        $po = null;
        if ($data['receipt_authority_type'] === 'po') {
            $po = PurchaseOrder::findOrFail($data['purchase_order_id'] ?? 0);
            if (in_array($po->status, ['closed', 'cancelled', 'fully_received'], true)) {
                throw ApiException::businessRule("Purchase order {$po->po_number} is {$po->status}.");
            }
            $data['supplier_id'] = $po->supplier_id;
        } elseif (blank($data['receipt_authority_ref'] ?? null)) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                'receipt_authority_ref' => ['A reference (donation letter, transfer note, opening balance approval) is required when there is no PO.'],
            ]);
        }

        $this->assertNotDuplicateInvoice($data['supplier_id'] ?? null, $data['invoice_no'] ?? null, $data['delivery_note_no'] ?? null);

        return DB::transaction(function () use ($data, $po, $officer) {
            $grn = Grn::create([
                'grn_no' => DocumentNumber::next('GRN'),
                'receipt_authority_type' => $data['receipt_authority_type'],
                'purchase_order_id' => $po?->id,
                'receipt_authority_ref' => $data['receipt_authority_ref'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'store_id' => $data['store_id'],
                'receipt_on' => $data['receipt_on'],
                'invoice_no' => $data['invoice_no'] ?? null,
                'invoice_on' => $data['invoice_on'] ?? null,
                'delivery_note_no' => $data['delivery_note_no'] ?? null,
                'received_by_officer_id' => $officer->id,
                'status' => 'draft',
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->writeLines($grn, $data['lines'], $po);
            $this->audit->log(AuditAction::Created, $grn, null, ['grn_no' => $grn->grn_no, 'store_id' => $grn->store_id], $data['reason'] ?? null);

            return $grn;
        });
    }

    public function updateDraft(Grn $grn, array $data): Grn
    {
        $this->assertStatus($grn, ['draft']);
        $this->actor->authorize('grn.create', Scope::store($grn->store_id));

        return DB::transaction(function () use ($grn, $data) {
            $grn->fill(array_intersect_key($data, array_flip(['receipt_on', 'invoice_no', 'invoice_on', 'delivery_note_no', 'remarks', 'receipt_authority_ref'])));
            $this->assertNotDuplicateInvoice($grn->supplier_id, $grn->invoice_no, $grn->delivery_note_no, $grn->id);
            $grn->save();

            if (isset($data['lines'])) {
                GrnLine::where('grn_id', $grn->id)->delete();
                $this->writeLines($grn, $data['lines'], $grn->purchaseOrder);
            }

            return $grn;
        });
    }

    public function submit(Grn $grn): Grn
    {
        $this->assertStatus($grn, ['draft']);
        $this->actor->authorize('grn.create', Scope::store($grn->store_id));

        if ($grn->lines()->count() === 0) {
            throw ApiException::businessRule('Add at least one line before submitting.');
        }

        return DB::transaction(function () use ($grn) {
            $grn->update(['status' => 'awaiting_inspection']);
            $this->approvals->start($grn, 'GRN', $grn->version_no, $grn->grand_total);
            $this->audit->log(AuditAction::StatusChanged, $grn, ['status' => 'draft'], ['status' => 'awaiting_inspection']);

            return $grn;
        });
    }

    /**
     * @param  array<int, array{id: int, accepted_qty: mixed, rejected_qty: mixed, condition_note?: ?string, rejection_reason?: ?string}>  $lines
     */
    public function inspect(Grn $grn, array $lines, ?string $remarks): Grn
    {
        $this->assertStatus($grn, ['awaiting_inspection', 'awaiting_approval']);
        $this->actor->authorize('grn.inspect', Scope::store($grn->store_id));
        $officer = $this->actor->officer();

        return DB::transaction(function () use ($grn, $lines, $remarks, $officer) {
            $byId = collect($lines)->keyBy('id');

            foreach ($grn->lines as $line) {
                $input = $byId->get($line->id);
                if (! $input) {
                    throw ApiException::businessRule("Inspection result missing for line {$line->line_no}.");
                }

                $accepted = Dec::n($input['accepted_qty']);
                $rejected = Dec::n($input['rejected_qty'] ?? 0);
                $pending = Dec::sub(Dec::sub($line->delivered_qty, $accepted), $rejected);

                if (Dec::cmp($accepted, 0) < 0 || Dec::cmp($rejected, 0) < 0 || Dec::cmp($pending, 0) < 0) {
                    throw ApiException::businessRule("Line {$line->line_no}: accepted + rejected cannot exceed delivered ({$line->delivered_qty}).");
                }
                if (Dec::isPositive($rejected) && blank($input['rejection_reason'] ?? null)) {
                    throw ApiException::businessRule("Line {$line->line_no}: give a reason for the rejected quantity.");
                }

                $line->update([
                    'accepted_qty' => $accepted,
                    'rejected_qty' => $rejected,
                    'pending_qty' => $pending,
                    'condition_note' => $input['condition_note'] ?? null,
                    'rejection_reason' => $input['rejection_reason'] ?? null,
                ]);
            }

            $grn->update(['status' => 'awaiting_approval', 'inspected_by_officer_id' => $officer->id]);
            $this->audit->log(AuditAction::Updated, $grn, null, ['inspected' => true], $remarks);

            return $grn->fresh('lines');
        });
    }

    /** Independent approval and posting of accepted stock. Idempotent. */
    public function approvePost(Grn $grn, string $idempotencyKey, ?string $remarks): Grn
    {
        return DB::transaction(function () use ($grn, $idempotencyKey, $remarks) {
            $grn = Grn::whereKey($grn->id)->lockForUpdate()->firstOrFail();

            if (Idempotency::alreadyPosted($grn, $idempotencyKey)) {
                return $grn;
            }
            $this->assertStatus($grn, ['awaiting_approval']);

            $this->approvals->decide($grn, $grn->version_no, 'approve', [
                'scope' => Scope::store($grn->store_id),
                'owner_officer_id' => $grn->received_by_officer_id,
                'remarks' => $remarks,
                'expect_permission' => 'grn.approve_post',
            ]);
            $officer = $this->actor->officer();

            foreach ($grn->lines()->with('item')->get() as $line) {
                if ($line->po_line_id) {
                    $poLine = PurchaseOrderLine::findOrFail($line->po_line_id);
                    $outstanding = Dec::sub($poLine->ordered_qty, $poLine->receivedQty($grn->id));
                    if (Dec::cmp($line->accepted_qty, $outstanding) > 0) {
                        throw ApiException::businessRule("Line {$line->line_no}: accepting {$line->accepted_qty} exceeds the outstanding PO quantity {$outstanding}. Amend the PO or record an authorised exception.");
                    }
                }

                if (! Dec::isPositive($line->accepted_qty)) {
                    continue;
                }

                $this->ledger->post([
                    'item_id' => $line->item_id,
                    'store_id' => $grn->store_id,
                    'batch_no' => $line->batch_no,
                    'expiry_on' => $line->expiry_on,
                    'qty' => $this->units->toBase($line->item, $line->unit_id, $line->accepted_qty),
                    'movement_type' => $grn->receipt_authority_type === 'opening_balance' ? 'opening' : 'receipt',
                    'event_on' => $grn->receipt_on,
                    'source_type' => 'grns',
                    'source_id' => $grn->id,
                    'source_line_id' => $line->id,
                    'document_no' => $grn->grn_no,
                ], $officer);
            }

            $grn->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posting_on' => now()->toDateString(),
                'authorised_by_officer_id' => $officer->id,
                'authorised_designation_snapshot' => $officer->designation?->name,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($grn->purchaseOrder) {
                $this->purchaseOrders->refreshStatus($grn->purchaseOrder);
            }

            $this->audit->log(AuditAction::Posted, $grn, ['status' => 'awaiting_approval'], ['status' => 'posted'], $remarks);

            return $grn->fresh('lines');
        });
    }

    public function reject(Grn $grn, string $reason): Grn
    {
        $this->assertStatus($grn, ['awaiting_inspection', 'awaiting_approval']);

        return DB::transaction(function () use ($grn, $reason) {
            $this->approvals->decide($grn, $grn->version_no, 'reject', [
                'scope' => Scope::store($grn->store_id),
                'owner_officer_id' => $grn->received_by_officer_id,
                'remarks' => $reason,
            ]);
            $grn->update(['status' => 'rejected']);
            $this->audit->log(AuditAction::StatusChanged, $grn, null, ['status' => 'rejected'], $reason);

            return $grn;
        });
    }

    /** Linked reversal: the ledger keeps both entries (rule 8). */
    public function reverse(Grn $grn, string $reason): Grn
    {
        $this->assertStatus($grn, ['posted']);
        $this->actor->authorize('grn.reverse', Scope::store($grn->store_id));

        return DB::transaction(function () use ($grn, $reason) {
            $grn = Grn::whereKey($grn->id)->lockForUpdate()->firstOrFail();
            $this->ledger->reverseSource('grns', $grn->id, $this->actor->officer(), 'Reversal: '.$reason, $grn->grn_no);
            $grn->update(['status' => 'reversed']);
            if ($grn->purchaseOrder) {
                $this->purchaseOrders->refreshStatus($grn->purchaseOrder);
            }
            $this->audit->log(AuditAction::Reversed, $grn, ['status' => 'posted'], ['status' => 'reversed'], $reason);

            return $grn;
        });
    }

    private function writeLines(Grn $grn, array $lines, ?PurchaseOrder $po): void
    {
        $totals = ['subtotal' => '0', 'discount_total' => '0', 'tax_total' => '0', 'other_charges_total' => '0'];

        foreach (array_values($lines) as $i => $line) {
            $item = Item::findOrFail($line['item_id']);

            if (! in_array((int) $line['unit_id'], $this->units->allowedUnitIds($item), true)) {
                throw ApiException::businessRule('Line '.($i + 1).": {$item->item_code} has no approved conversion for that unit.");
            }

            if (! empty($line['po_line_id'])) {
                $poLine = PurchaseOrderLine::find($line['po_line_id']);
                if (! $poLine || ! $po || $poLine->purchase_order_id !== $po->id || $poLine->item_id !== $item->id) {
                    throw ApiException::businessRule('Line '.($i + 1).': the PO line does not belong to this purchase order / item.');
                }
            } elseif ($po) {
                throw ApiException::businessRule('Line '.($i + 1).': choose the purchase order line being received.');
            }

            if ($item->has_expiry && empty($line['expiry_on'])) {
                throw ApiException::businessRule('Line '.($i + 1).": {$item->item_code} needs an expiry date.");
            }

            $gross = Dec::mul($line['delivered_qty'], $line['unit_price'] ?? 0, 2);
            $lineTotal = Dec::sub(Dec::add(Dec::add($gross, $line['tax'] ?? 0, 2), $line['other_charges'] ?? 0, 2), $line['discount'] ?? 0, 2);

            GrnLine::create([
                'grn_id' => $grn->id,
                'po_line_id' => $line['po_line_id'] ?? null,
                'line_no' => $i + 1,
                'item_id' => $item->id,
                'unit_id' => $line['unit_id'],
                'delivered_qty' => $line['delivered_qty'],
                'accepted_qty' => 0,
                'rejected_qty' => 0,
                'pending_qty' => $line['delivered_qty'],
                'batch_no' => $line['batch_no'] ?? null,
                'expiry_on' => $line['expiry_on'] ?? null,
                'unit_price' => $line['unit_price'] ?? 0,
                'discount' => $line['discount'] ?? 0,
                'tax' => $line['tax'] ?? 0,
                'other_charges' => $line['other_charges'] ?? 0,
                'line_total' => $lineTotal,
            ]);

            $totals['subtotal'] = Dec::add($totals['subtotal'], $gross, 2);
            $totals['discount_total'] = Dec::add($totals['discount_total'], $line['discount'] ?? 0, 2);
            $totals['tax_total'] = Dec::add($totals['tax_total'], $line['tax'] ?? 0, 2);
            $totals['other_charges_total'] = Dec::add($totals['other_charges_total'], $line['other_charges'] ?? 0, 2);
        }

        $totals['grand_total'] = Dec::sub(Dec::add(Dec::add($totals['subtotal'], $totals['tax_total'], 2), $totals['other_charges_total'], 2), $totals['discount_total'], 2);
        $grn->update($totals);
    }

    private function assertNotDuplicateInvoice(?int $supplierId, ?string $invoiceNo, ?string $deliveryNote, ?int $exceptId = null): void
    {
        if (! $supplierId) {
            return;
        }

        foreach (['invoice_no' => $invoiceNo, 'delivery_note_no' => $deliveryNote] as $column => $value) {
            if ($value && Grn::where('supplier_id', $supplierId)->where($column, $value)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists()) {
                $label = $column === 'invoice_no' ? 'invoice' : 'delivery note';
                throw ApiException::conflict("This supplier's {$label} {$value} is already recorded on another GRN.", 'DUPLICATE_POSTING');
            }
        }
    }

    private function assertStatus(Grn $grn, array $allowed): void
    {
        if (! in_array($grn->status, $allowed, true)) {
            throw ApiException::businessRule("GRN {$grn->grn_no} is {$grn->status}; this action needs: ".implode(' / ', $allowed).'.');
        }
    }
}
