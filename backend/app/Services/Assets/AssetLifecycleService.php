<?php

namespace App\Services\Assets;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\AssetVerificationSession;
use App\Models\Disposal;
use App\Models\RepairCase;
use App\Models\Valuation;
use App\Models\Warranty;
use App\Services\Access\Actor;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Warranty, verification, valuation and disposal (Phase 7). */
class AssetLifecycleService
{
    public function __construct(
        private readonly Actor $actor,
        private readonly ApprovalService $approvals,
        private readonly CustodyService $custody,
        private readonly AuditLogger $audit,
    ) {}

    // ---- Warranty -----------------------------------------------------------

    public function addWarranty(array $data): Warranty
    {
        $this->actor->authorize('warranty.manage');
        $asset = $this->asset($data['asset_id']);

        return DB::transaction(function () use ($asset, $data) {
            $warranty = Warranty::create([
                'asset_id' => $asset->id,
                'component' => $data['component'] ?? null,
                'provider_supplier_id' => $data['provider_supplier_id'] ?? null,
                'coverage_type' => $data['coverage_type'] ?? null,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'terms' => $data['terms'] ?? null,
                'status' => Carbon::parse($data['ends_on'])->isPast() ? 'expired' : 'active',
            ]);
            $this->audit->log(AuditAction::Created, $warranty, null, $warranty->only(['asset_id', 'starts_on', 'ends_on']));

            return $warranty;
        });
    }

    /** An extension is a NEW linked row; the earlier coverage stays as it was. */
    public function extendWarranty(Warranty $warranty, array $data): Warranty
    {
        $this->actor->authorize('warranty.manage');
        if (Carbon::parse($data['ends_on'])->lte($warranty->ends_on)) {
            throw ApiException::businessRule('An extension must end after the current coverage ('.$warranty->ends_on->toDateString().').');
        }

        return DB::transaction(function () use ($warranty, $data) {
            $extension = Warranty::create([
                'asset_id' => $warranty->asset_id,
                'component' => $warranty->component,
                'provider_supplier_id' => $data['provider_supplier_id'] ?? $warranty->provider_supplier_id,
                'coverage_type' => $data['coverage_type'] ?? $warranty->coverage_type,
                'starts_on' => $warranty->ends_on->copy()->addDay()->toDateString(),
                'ends_on' => $data['ends_on'],
                'terms' => $data['terms'] ?? $warranty->terms,
                'extends_warranty_id' => $warranty->id,
                'status' => 'active',
            ]);
            $this->audit->log(AuditAction::Created, $extension, null, ['extends' => $warranty->id, 'ends_on' => $data['ends_on']]);

            return $extension;
        });
    }

    // ---- Verification -------------------------------------------------------

    public function openVerification(array $data): AssetVerificationSession
    {
        $this->actor->authorize('verification.perform');

        return AssetVerificationSession::create([
            'session_no' => DocumentNumber::next('VER'),
            'scope_note' => $data['scope_note'] ?? null,
            'conducted_on' => $data['conducted_on'] ?? now()->toDateString(),
            'lead_officer_id' => $this->actor->officerId(),
            'status' => 'open',
        ]);
    }

    /** Record what was found for one asset; the expected values come from the register. */
    public function verify(AssetVerificationSession $session, array $data): AssetVerification
    {
        $this->actor->authorize('verification.perform');
        if ($session->status !== 'open') {
            throw ApiException::businessRule('This verification session is closed.');
        }
        $asset = $this->asset($data['asset_id']);

        $result = $data['result'] ?? null;
        if ($result === null) {
            $locationMatches = empty($data['found_location_id']) || (int) $data['found_location_id'] === (int) $asset->location_id;
            $custodianMatches = empty($data['found_custodian_officer_id']) || (int) $data['found_custodian_officer_id'] === (int) $asset->custodian_officer_id;
            $result = $locationMatches && $custodianMatches ? 'match' : 'mismatch';
        }

        return DB::transaction(function () use ($session, $asset, $data, $result) {
            $verification = AssetVerification::create([
                'session_id' => $session->id,
                'asset_id' => $asset->id,
                'verified_by_officer_id' => $this->actor->officerId(),
                'verified_at' => now(),
                'expected_location_id' => $asset->location_id,
                'found_location_id' => $data['found_location_id'] ?? null,
                'expected_custodian_officer_id' => $asset->custodian_officer_id,
                'found_custodian_officer_id' => $data['found_custodian_officer_id'] ?? null,
                'condition_found' => $data['condition_found'] ?? null,
                'result' => $result,
                'follow_up' => $data['follow_up'] ?? null,
            ]);

            $changes = ['last_verified_at' => now()];
            if (! empty($data['condition_found']) && in_array($data['condition_found'], Asset::CONDITIONS, true)) {
                $changes['condition'] = $data['condition_found'];
            }
            if ($result === 'missing') {
                $changes['operational_status'] = 'lost';
            }
            $asset->update($changes);
            if ($result === 'missing') {
                $this->custody->record($asset, 'Not found at verification', 'asset_verifications', $verification->id);
            }

            return $verification;
        });
    }

    public function closeVerification(AssetVerificationSession $session): AssetVerificationSession
    {
        $this->actor->authorize('verification.perform');
        $session->update(['status' => 'closed']);

        return $session;
    }

    // ---- Valuation ----------------------------------------------------------

    /** A new valuation is a new row; earlier values are never overwritten. */
    public function proposeValuation(array $data): Valuation
    {
        $this->actor->authorize('valuation.manage');
        $asset = $this->asset($data['asset_id']);
        $previous = Valuation::where(['asset_id' => $asset->id, 'status' => 'approved'])->orderByDesc('effective_on')->orderByDesc('id')->first();

        return DB::transaction(function () use ($asset, $data, $previous) {
            $valuation = Valuation::create([
                'asset_id' => $asset->id,
                'previous_value' => $previous?->new_value,
                'new_value' => $data['new_value'],
                'effective_on' => $data['effective_on'],
                'decision_ref' => $data['decision_ref'],
                'assessed_by_officer_id' => $this->actor->officerId(),
                'status' => 'submitted',
            ]);
            $this->approvals->start($valuation, 'VALUE_CHANGE', 1, $data['new_value']);
            $this->audit->log(AuditAction::Created, $valuation, null, ['asset' => $asset->asset_no, 'new_value' => $data['new_value']], $data['decision_ref']);

            return $valuation;
        });
    }

    public function decideValuation(Valuation $valuation, bool $approve, ?string $remarks): Valuation
    {
        if ($valuation->status !== 'submitted') {
            throw ApiException::businessRule('This valuation has already been decided.');
        }

        return DB::transaction(function () use ($valuation, $approve, $remarks) {
            $this->approvals->decide($valuation, 1, $approve ? 'approve' : 'reject', [
                'owner_officer_id' => $valuation->assessed_by_officer_id,
                'remarks' => $remarks,
            ]);
            $valuation->update([
                'status' => $approve ? 'approved' : 'rejected',
                'approved_by_officer_id' => $this->actor->officerId(),
                'approved_at' => now(),
            ]);
            if ($approve) {
                $valuation->asset->update(['value_reference' => $valuation->decision_ref]);
            }
            $this->audit->log($approve ? AuditAction::Approved : AuditAction::StatusChanged, $valuation, null, ['status' => $valuation->status], $remarks);

            return $valuation;
        });
    }

    // ---- Disposal -----------------------------------------------------------

    public function proposeDisposal(array $data): Disposal
    {
        $this->actor->authorize('disposal.manage');
        $asset = $this->asset($data['asset_id']);

        if ($asset->operational_status === 'disposed') {
            throw ApiException::businessRule("Asset {$asset->asset_no} is already disposed.");
        }
        if (Disposal::where('asset_id', $asset->id)->whereIn('status', ['proposed', 'approved'])->exists()) {
            throw ApiException::businessRule('A disposal is already in progress for this asset.');
        }

        return DB::transaction(function () use ($asset, $data) {
            $disposal = Disposal::create([
                'asset_id' => $asset->id,
                'method' => $data['method'],
                'decision_ref' => $data['decision_ref'],
                'reason' => $data['reason'] ?? null,
                'proposed_by_officer_id' => $this->actor->officerId(),
                'status' => 'proposed',
            ]);
            $this->approvals->start($disposal, 'DISPOSAL', 1);
            $this->audit->log(AuditAction::Created, $disposal, null, ['asset' => $asset->asset_no, 'method' => $data['method']], $data['reason'] ?? null);

            return $disposal;
        });
    }

    public function decideDisposal(Disposal $disposal, bool $approve, ?string $remarks): Disposal
    {
        if ($disposal->status !== 'proposed') {
            throw ApiException::businessRule('This disposal has already been decided.');
        }

        return DB::transaction(function () use ($disposal, $approve, $remarks) {
            $this->approvals->decide($disposal, 1, $approve ? 'approve' : 'reject', [
                'owner_officer_id' => $disposal->proposed_by_officer_id,
                'remarks' => $remarks,
            ]);
            $disposal->update([
                'status' => $approve ? 'approved' : 'rejected',
                'approved_by_officer_id' => $this->actor->officerId(),
                'approved_at' => now(),
            ]);
            $this->audit->log($approve ? AuditAction::Approved : AuditAction::StatusChanged, $disposal, null, ['status' => $disposal->status], $remarks);

            return $disposal;
        });
    }

    /** Formally retire the asset once the approved disposal has happened. */
    public function completeDisposal(Disposal $disposal, array $data): Disposal
    {
        $this->actor->authorize('disposal.manage');
        if ($disposal->status !== 'approved') {
            throw ApiException::businessRule('Only approved disposals can be completed.');
        }

        return DB::transaction(function () use ($disposal, $data) {
            $asset = Asset::whereKey($disposal->asset_id)->lockForUpdate()->firstOrFail();
            if (AssetTransfer::where('asset_id', $asset->id)->whereIn('status', AssetTransfer::OPEN)->exists()
                || RepairCase::where('asset_id', $asset->id)->where('status', '!=', 'closed')->exists()) {
                throw ApiException::businessRule('Close open transfers and repairs before disposing of the asset.');
            }

            $disposal->update(['status' => 'completed', 'disposed_on' => $data['disposed_on'] ?? now()->toDateString(), 'outcome' => $data['outcome'] ?? null]);
            $this->custody->move($asset, [
                'operational_status' => 'disposed',
                'store_id' => null,
                'custodian_officer_id' => null,
            ], 'Disposed ('.$disposal->method.')', 'disposals', $disposal->id);
            $this->audit->log(AuditAction::StatusChanged, $disposal, null, ['status' => 'completed']);

            return $disposal;
        });
    }

    private function asset(mixed $id): Asset
    {
        return Asset::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
    }
}
