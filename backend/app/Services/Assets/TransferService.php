<?php

namespace App\Services\Assets;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Discrepancy;
use App\Models\RepairCase;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Support\DocumentNumber;
use App\Support\Find;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Transfers and temporary loans: submitted → approved → dispatched (asset In
 * Transit) → received (custody changes only now) / refused. Approval ≠ moved
 * (rule 12). An asset cannot be in two open transfers or a transfer and an
 * open repair (rule 13).
 */
class TransferService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly ApprovalService $approvals,
        private readonly CustodyService $custody,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data): AssetTransfer
    {
        $asset = Find::byKey(Asset::class, $data['asset_id']);

        if ($asset->responsible_department_id) {
            $this->actor->authorize('transfer.create', Scope::department($asset->responsible_department_id));
        } else {
            $this->actor->authorize('transfer.create');
        }
        $officer = $this->actor->officer();

        if (in_array($asset->operational_status, ['disposed', 'lost', 'in_transit', 'under_repair'], true)) {
            throw ApiException::businessRule("Asset {$asset->asset_no} is {$asset->operational_status} and cannot be transferred.");
        }
        if (RepairCase::where('asset_id', $asset->id)->where('status', '!=', 'closed')->exists()) {
            throw ApiException::businessRule("Asset {$asset->asset_no} has an open repair case.");
        }
        if (($data['transfer_type'] ?? 'transfer') === 'loan' && empty($data['expected_return_on'])) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, ['expected_return_on' => ['A loan needs an expected return date.']]);
        }
        if (empty($data['to_department_id']) && empty($data['to_location_id']) && empty($data['to_custodian_officer_id'])) {
            throw ApiException::businessRule('Say where the asset is going (department, location or custodian).');
        }

        try {
            return DB::transaction(function () use ($asset, $data, $officer) {
                $transfer = AssetTransfer::create([
                    'transfer_no' => DocumentNumber::next('TRF'),
                    'asset_id' => $asset->id,
                    'transfer_type' => $data['transfer_type'] ?? 'transfer',
                    'from_department_id' => $asset->responsible_department_id,
                    'from_location_id' => $asset->location_id,
                    'from_custodian_officer_id' => $asset->custodian_officer_id,
                    'to_department_id' => $data['to_department_id'] ?? $asset->responsible_department_id,
                    'to_location_id' => $data['to_location_id'] ?? null,
                    'to_custodian_officer_id' => $data['to_custodian_officer_id'] ?? null,
                    'status' => 'submitted',
                    'requested_by_officer_id' => $officer->id,
                    'accessories' => $data['accessories'] ?? null,
                    'authority_ref' => $data['authority_ref'] ?? null,
                    'reason' => $data['reason'] ?? null,
                    'expected_return_on' => $data['expected_return_on'] ?? null,
                ]);
                $this->approvals->start($transfer, 'ASSET_TRANSFER', 1);
                $this->audit->log(AuditAction::Created, $transfer, null, ['transfer_no' => $transfer->transfer_no, 'asset' => $asset->asset_no]);

                return $transfer;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw ApiException::conflict("Asset {$asset->asset_no} is already in an open transfer.");
            }
            throw $e;
        }
    }

    public function approve(AssetTransfer $transfer, ?string $remarks): AssetTransfer
    {
        $this->assertStatus($transfer, ['submitted']);

        return DB::transaction(function () use ($transfer, $remarks) {
            $this->approvals->decide($transfer, 1, 'approve', [
                'scope' => $transfer->from_department_id ? Scope::department($transfer->from_department_id) : null,
                'owner_officer_id' => $transfer->requested_by_officer_id,
                'remarks' => $remarks,
                'expect_permission' => 'transfer.approve',
            ]);
            $transfer->update(['status' => 'approved', 'approved_by_officer_id' => $this->actor->officerId()]);
            $this->audit->log(AuditAction::Approved, $transfer, null, ['status' => 'approved'], $remarks);

            return $transfer;
        });
    }

    public function reject(AssetTransfer $transfer, string $remarks): AssetTransfer
    {
        $this->assertStatus($transfer, ['submitted']);

        return DB::transaction(function () use ($transfer, $remarks) {
            $this->approvals->decide($transfer, 1, 'reject', [
                'scope' => $transfer->from_department_id ? Scope::department($transfer->from_department_id) : null,
                'owner_officer_id' => $transfer->requested_by_officer_id,
                'remarks' => $remarks,
            ]);
            $transfer->update(['status' => 'cancelled']);
            $this->audit->log(AuditAction::StatusChanged, $transfer, null, ['status' => 'cancelled'], $remarks);

            return $transfer;
        });
    }

    /** Asset leaves: status In Transit, location NOT changed yet. */
    public function dispatch(AssetTransfer $transfer, array $data): AssetTransfer
    {
        $this->assertStatus($transfer, ['approved']);
        $this->actor->authorize('transfer.dispatch');
        $officer = $this->actor->officer();

        return DB::transaction(function () use ($transfer, $data, $officer) {
            $asset = Asset::whereKey($transfer->asset_id)->lockForUpdate()->firstOrFail();
            $transfer->update([
                'status' => 'dispatched',
                'dispatched_by_officer_id' => $officer->id,
                'dispatched_at' => now(),
                'condition_on_dispatch' => $data['condition'] ?? $asset->condition,
                'accessories' => $data['accessories'] ?? $transfer->accessories,
            ]);
            $this->custody->move($asset, [
                'operational_status' => 'in_transit',
                'expected_location_id' => $transfer->to_location_id,
            ], 'Dispatched on transfer', 'asset_transfers', $transfer->id);
            $this->audit->log(AuditAction::StatusChanged, $transfer, null, ['status' => 'dispatched']);

            return $transfer;
        });
    }

    /** Receiver confirms condition and serial; only now do location / custodian change. */
    public function receive(AssetTransfer $transfer, array $data): AssetTransfer
    {
        $this->assertStatus($transfer, ['dispatched']);
        $this->actor->authorize('transfer.receive', $transfer->to_department_id ? Scope::department($transfer->to_department_id) : null);
        $officer = $this->actor->officer();

        if (! ($data['serial_verified'] ?? false)) {
            throw ApiException::businessRule('Confirm the serial / asset number matches before receiving.');
        }

        return DB::transaction(function () use ($transfer, $data, $officer) {
            $asset = Asset::whereKey($transfer->asset_id)->lockForUpdate()->firstOrFail();
            $transfer->update([
                'status' => 'received',
                'received_by_officer_id' => $officer->id,
                'received_at' => now(),
                'condition_on_receipt' => $data['condition'],
                'serial_verified' => true,
            ]);

            $this->custody->move($asset, [
                'operational_status' => $transfer->transfer_type === 'loan' ? 'on_loan' : 'in_use',
                'responsible_department_id' => $transfer->transfer_type === 'loan' ? $asset->responsible_department_id : $transfer->to_department_id,
                'location_id' => $transfer->to_location_id ?? $asset->location_id,
                'custodian_officer_id' => $transfer->to_custodian_officer_id ?? $asset->custodian_officer_id,
                'store_id' => null,
                'expected_location_id' => null,
                'condition' => in_array($data['condition'], Asset::CONDITIONS, true) ? $data['condition'] : null,
            ], $transfer->transfer_type === 'loan' ? 'Received on loan' : 'Received on transfer', 'asset_transfers', $transfer->id);

            $this->audit->log(AuditAction::StatusChanged, $transfer, null, ['status' => 'received', 'condition' => $data['condition']]);

            return $transfer;
        });
    }

    /** Receiver refuses: asset goes back to its previous state, a discrepancy opens. */
    public function refuse(AssetTransfer $transfer, string $reason): AssetTransfer
    {
        $this->assertStatus($transfer, ['dispatched']);
        $this->actor->authorize('transfer.receive', $transfer->to_department_id ? Scope::department($transfer->to_department_id) : null);

        return DB::transaction(function () use ($transfer, $reason) {
            $asset = Asset::whereKey($transfer->asset_id)->lockForUpdate()->firstOrFail();
            $transfer->update(['status' => 'refused', 'received_by_officer_id' => $this->actor->officerId(), 'received_at' => now()]);
            $this->custody->move($asset, [
                'operational_status' => $asset->responsible_department_id ? 'in_use' : 'in_store',
                'expected_location_id' => null,
            ], 'Transfer refused', 'asset_transfers', $transfer->id);
            Discrepancy::create([
                'discrepancy_type' => 'transfer_refused',
                'source_type' => 'asset_transfers',
                'source_id' => $transfer->id,
                'description' => "{$transfer->transfer_no}: {$reason}",
                'status' => 'open',
            ]);
            $this->audit->log(AuditAction::StatusChanged, $transfer, null, ['status' => 'refused'], $reason);

            return $transfer;
        });
    }

    /** End of a loan: the asset comes back to the lending department. */
    public function returnLoan(AssetTransfer $transfer, ?string $remarks): AssetTransfer
    {
        if ($transfer->transfer_type !== 'loan' || $transfer->status !== 'received') {
            throw ApiException::businessRule('Only received loans can be returned.');
        }
        $this->actor->authorize('loan.manage');

        return DB::transaction(function () use ($transfer, $remarks) {
            $asset = Asset::whereKey($transfer->asset_id)->lockForUpdate()->firstOrFail();
            $transfer->update(['status' => 'returned', 'returned_at' => now(), 'return_acknowledged_by_officer_id' => $this->actor->officerId()]);
            $this->custody->move($asset, [
                'operational_status' => 'in_use',
                'location_id' => $transfer->from_location_id,
                'custodian_officer_id' => $transfer->from_custodian_officer_id,
            ], 'Loan returned', 'asset_transfers', $transfer->id);
            $this->audit->log(AuditAction::StatusChanged, $transfer, null, ['status' => 'returned'], $remarks);

            return $transfer;
        });
    }

    private function assertStatus(AssetTransfer $transfer, array $allowed): void
    {
        if (! in_array($transfer->status, $allowed, true)) {
            throw ApiException::businessRule("Transfer {$transfer->transfer_no} is {$transfer->status}.");
        }
    }
}
