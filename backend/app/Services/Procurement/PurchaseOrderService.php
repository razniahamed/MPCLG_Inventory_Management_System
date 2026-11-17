<?php

namespace App\Services\Procurement;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Audit\AuditLogger;
use App\Support\Dec;
use Illuminate\Support\Facades\DB;

/**
 * Reference copies of approved purchase orders. The PO process itself stays
 * outside the system; we keep what GRNs are checked against.
 */
class PurchaseOrderService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data): PurchaseOrder
    {
        if (PurchaseOrder::where(['supplier_id' => $data['supplier_id'], 'po_number' => $data['po_number']])->exists()) {
            throw ApiException::conflict('This supplier already has a purchase order with that number.');
        }

        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'po_number' => $data['po_number'],
                'po_date' => $data['po_date'],
                'supplier_id' => $data['supplier_id'],
                'approval_doc_ref' => $data['approval_doc_ref'] ?? null,
                'expected_delivery_on' => $data['expected_delivery_on'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => 'open',
            ]);

            foreach (array_values($data['lines']) as $i => $line) {
                $total = Dec::sub(
                    Dec::add(Dec::add(Dec::mul($line['ordered_qty'], $line['unit_price'] ?? 0, 2), $line['tax'] ?? 0, 2), $line['other_charges'] ?? 0, 2),
                    $line['discount'] ?? 0,
                    2,
                );
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'line_no' => $i + 1,
                    'item_id' => $line['item_id'],
                    'unit_id' => $line['unit_id'],
                    'ordered_qty' => $line['ordered_qty'],
                    'unit_price' => $line['unit_price'] ?? null,
                    'discount' => $line['discount'] ?? 0,
                    'tax' => $line['tax'] ?? 0,
                    'other_charges' => $line['other_charges'] ?? 0,
                    'line_total' => $total,
                ]);
            }

            $this->audit->log(AuditAction::Created, $po, null, ['po_number' => $po->po_number, 'lines' => count($data['lines'])], $data['reason'] ?? null);

            return $po->load('lines');
        });
    }

    public function setStatus(PurchaseOrder $po, string $status, string $reason): PurchaseOrder
    {
        if (! in_array($status, ['closed', 'cancelled'], true)) {
            throw ApiException::businessRule('A purchase order can only be closed or cancelled by hand.');
        }
        if ($status === 'cancelled' && $po->grns()->where('status', 'posted')->exists()) {
            throw ApiException::businessRule('Goods have been received against this order; close it instead of cancelling.');
        }

        return DB::transaction(function () use ($po, $status, $reason) {
            $old = $po->status;
            $po->update(['status' => $status]);
            $this->audit->log(AuditAction::StatusChanged, $po, ['status' => $old], ['status' => $status], $reason);

            return $po;
        });
    }

    /** Recalculate open / partly / fully received from posted GRNs. */
    public function refreshStatus(PurchaseOrder $po): void
    {
        if (in_array($po->status, ['closed', 'cancelled'], true)) {
            return;
        }

        $lines = $po->lines()->get();
        $received = $lines->map(fn (PurchaseOrderLine $l) => $l->receivedQty());
        $any = $received->contains(fn ($q) => Dec::isPositive($q));
        $all = $lines->every(fn (PurchaseOrderLine $l) => Dec::cmp($l->receivedQty(), $l->ordered_qty) >= 0);

        $po->update(['status' => $all ? 'fully_received' : ($any ? 'partly_received' : 'open')]);
    }
}
