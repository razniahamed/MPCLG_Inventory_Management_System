<?php

namespace App\Services\Assets;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\RepairCase;
use App\Models\RepairUpdate;
use App\Models\Supplier;
use App\Models\Warranty;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Support\Dec;
use App\Support\DocumentNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Repairs: reported → assessed → (approved) → dispatched → returned → tested →
 * closed. Repair changes physical custody but NOT the responsible department;
 * a repair never restarts the warranty; closing requires a passed test (rule 15).
 */
class RepairService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly ApprovalService $approvals,
        private readonly CustodyService $custody,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $data): RepairCase
    {
        $asset = Asset::where('public_id', $data['asset_id'])->orWhere('id', $data['asset_id'])->firstOrFail();
        $this->actor->authorize('repair.create', $asset->responsible_department_id ? Scope::department($asset->responsible_department_id) : null);
        $officer = $this->actor->officer();

        if (in_array($asset->operational_status, ['disposed', 'lost'], true)) {
            throw ApiException::businessRule("Asset {$asset->asset_no} is {$asset->operational_status}.");
        }
        if (AssetTransfer::where('asset_id', $asset->id)->whereIn('status', AssetTransfer::OPEN)->exists()) {
            throw ApiException::businessRule("Asset {$asset->asset_no} is in an open transfer.");
        }

        $warranty = Warranty::where('asset_id', $asset->id)->where('status', '!=', 'void')
            ->whereDate('starts_on', '<=', now())->whereDate('ends_on', '>=', now())
            ->orderByDesc('ends_on')->first();

        try {
            return DB::transaction(function () use ($asset, $data, $officer, $warranty) {
                $case = RepairCase::create([
                    'case_no' => DocumentNumber::next('RPR'),
                    'asset_id' => $asset->id,
                    'fault_description' => $data['fault_description'],
                    'reported_by_officer_id' => $officer->id,
                    'warranty_id' => $warranty?->id,
                    'under_warranty' => $warranty !== null,
                    'provider_supplier_id' => $warranty?->provider_supplier_id,
                    'status' => 'reported',
                    'previous_status' => $asset->operational_status,
                ]);
                RepairUpdate::create(['repair_case_id' => $case->id, 'note' => 'Fault reported: '.$data['fault_description'], 'updated_by_officer_id' => $officer->id, 'event_at' => now()]);
                $this->audit->log(AuditAction::Created, $case, null, ['case_no' => $case->case_no, 'asset' => $asset->asset_no, 'under_warranty' => $warranty !== null]);

                return $case;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw ApiException::conflict("Asset {$asset->asset_no} already has an open repair case.");
            }
            throw $e;
        }
    }

    public function assess(RepairCase $case, array $data): RepairCase
    {
        $this->assertStatus($case, ['reported', 'assessed']);
        $this->actor->authorize('repair.update');

        return DB::transaction(function () use ($case, $data) {
            $provider = ! empty($data['provider_supplier_id']) ? Supplier::find($data['provider_supplier_id']) : $case->provider;
            $needsApproval = ! $case->under_warranty && Dec::isPositive($data['quotation_amount'] ?? 0);

            $case->update([
                'assessed_by_officer_id' => $this->actor->officerId(),
                'assessment' => $data['assessment'],
                'provider_supplier_id' => $provider?->id,
                'provider_address_snapshot' => $provider?->address,
                'provider_phone_snapshot' => $provider?->phone,
                'quotation_amount' => $data['quotation_amount'] ?? null,
                'expected_return_on' => $data['expected_return_on'] ?? null,
                'status' => $needsApproval ? 'awaiting_approval' : 'approved',
            ]);

            if ($needsApproval) {
                $this->approvals->start($case, 'REPAIR_DISPATCH', 1, $case->quotation_amount);
            }

            $this->note($case, 'Assessed: '.$data['assessment'].($needsApproval ? " (quotation {$case->quotation_amount}, awaiting approval)" : ''));

            return $case;
        });
    }

    public function approve(RepairCase $case, array $data): RepairCase
    {
        $this->assertStatus($case, ['awaiting_approval']);

        return DB::transaction(function () use ($case, $data) {
            $this->approvals->decide($case, 1, 'approve', [
                'owner_officer_id' => $case->reported_by_officer_id,
                'remarks' => $data['remarks'] ?? null,
                'authority_ref' => $data['expenditure_approval_ref'] ?? null,
                'expect_permission' => 'repair.approve',
            ]);
            $case->update([
                'status' => 'approved',
                'approved_by_officer_id' => $this->actor->officerId(),
                'expenditure_approval_ref' => $data['expenditure_approval_ref'] ?? null,
            ]);
            $this->note($case, 'Expenditure approved'.(! empty($data['expenditure_approval_ref']) ? " ({$data['expenditure_approval_ref']})" : ''));

            return $case;
        });
    }

    /** Custody moves to the provider; the responsible department stays the same. */
    public function dispatch(RepairCase $case, array $data): RepairCase
    {
        $this->assertStatus($case, ['approved']);
        $this->actor->authorize('repair.dispatch');

        return DB::transaction(function () use ($case, $data) {
            $asset = Asset::whereKey($case->asset_id)->lockForUpdate()->firstOrFail();
            $case->update([
                'status' => 'in_repair',
                'dispatched_at' => now(),
                'accessories_sent' => $data['accessories_sent'] ?? null,
                'expected_return_on' => $data['expected_return_on'] ?? $case->expected_return_on,
            ]);
            $this->custody->move($asset, ['operational_status' => 'under_repair'], 'Sent for repair', 'repair_cases', $case->id);
            $this->note($case, 'Dispatched to '.($case->provider?->name ?? 'repairer'));

            return $case;
        });
    }

    public function addUpdate(RepairCase $case, array $data): RepairUpdate
    {
        if ($case->status === 'closed') {
            throw ApiException::businessRule('The case is closed.');
        }
        $this->actor->authorize('repair.update');

        return RepairUpdate::create([
            'repair_case_id' => $case->id,
            'update_source' => $data['update_source'] ?? 'officer',
            'note' => $data['note'],
            'part_description' => $data['part_description'] ?? null,
            'cost' => $data['cost'] ?? null,
            'updated_by_officer_id' => $this->actor->officerId(),
            'event_at' => $data['event_at'] ?? now(),
        ]);
    }

    public function markReturned(RepairCase $case, array $data): RepairCase
    {
        $this->assertStatus($case, ['in_repair', 'test_failed']);
        $this->actor->authorize('repair.update');

        return DB::transaction(function () use ($case, $data) {
            $total = Dec::sum($case->updates()->whereNotNull('cost')->pluck('cost'), 2);
            $case->update([
                'status' => 'testing',
                'returned_at' => now(),
                'total_cost' => $data['total_cost'] ?? $total,
            ]);
            $this->note($case, 'Returned from repair; awaiting test');

            return $case;
        });
    }

    public function test(RepairCase $case, bool $passed, ?string $remarks): RepairCase
    {
        $this->assertStatus($case, ['testing']);
        $this->actor->authorize('repair.update');

        return DB::transaction(function () use ($case, $passed, $remarks) {
            $case->update([
                'status' => $passed ? 'returned' : 'test_failed',
                'test_result' => $passed ? 'passed' : 'failed',
                'tested_by_officer_id' => $this->actor->officerId(),
                'tested_at' => now(),
            ]);
            $this->note($case, 'Return test '.($passed ? 'PASSED' : 'FAILED').($remarks ? ": {$remarks}" : ''));

            return $case;
        });
    }

    /** Closing requires a passed return test. The warranty is not restarted. */
    public function close(RepairCase $case, ?string $outcome): RepairCase
    {
        $this->actor->authorize('repair.close');
        if ($case->status === 'reported' || $case->status === 'assessed') {
            // closed without repair (e.g. no fault found)
            return $this->closeCase($case, $outcome ?? 'Closed without repair', false);
        }
        if ($case->test_result !== 'passed' || $case->status !== 'returned') {
            throw ApiException::businessRule('A repair can only be closed after the returned asset passes its test.');
        }

        return $this->closeCase($case, $outcome ?? 'Repaired', true);
    }

    private function closeCase(RepairCase $case, string $outcome, bool $wasAway): RepairCase
    {
        return DB::transaction(function () use ($case, $outcome, $wasAway) {
            $asset = Asset::whereKey($case->asset_id)->lockForUpdate()->firstOrFail();
            $case->update(['status' => 'closed', 'outcome' => $outcome]);

            if ($wasAway || $asset->operational_status === 'under_repair') {
                $restore = in_array($case->previous_status, ['in_use', 'in_store', 'on_loan'], true) ? $case->previous_status : ($asset->responsible_department_id ? 'in_use' : 'in_store');
                $this->custody->move($asset, ['operational_status' => $restore], 'Back from repair', 'repair_cases', $case->id);
            }
            $this->note($case, 'Closed: '.$outcome);
            $this->audit->log(AuditAction::StatusChanged, $case, null, ['status' => 'closed', 'outcome' => $outcome]);

            return $case;
        });
    }

    private function note(RepairCase $case, string $note): void
    {
        RepairUpdate::create([
            'repair_case_id' => $case->id,
            'note' => $note,
            'updated_by_officer_id' => $this->actor->officerId(),
            'event_at' => now(),
        ]);
    }

    private function assertStatus(RepairCase $case, array $allowed): void
    {
        if (! in_array($case->status, $allowed, true)) {
            throw ApiException::businessRule("Repair {$case->case_no} is {$case->status}; this action needs: ".implode(' / ', $allowed).'.');
        }
    }
}
