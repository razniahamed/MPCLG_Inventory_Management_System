<?php

namespace App\Http\Controllers\Api\V1\Assets;

use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\Asset;
use App\Models\AssetVerificationSession;
use App\Models\Disposal;
use App\Models\RepairCase;
use App\Models\ServiceSchedule;
use App\Models\Valuation;
use App\Models\Warranty;
use App\Services\Access\Actor;
use App\Services\Approvals\ApprovalService;
use App\Services\Assets\AssetLifecycleService;
use App\Services\Assets\RepairService;
use App\Support\ApiResponse;
use App\Support\Find;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Warranty, repairs, servicing, verification, valuation and disposal (Phase 7). */
class MaintenanceController extends Controller
{
    private const ASSET = 'asset:id,public_id,asset_no,item_id,serial_no,operational_status,responsible_department_id';

    public function __construct(
        private readonly RepairService $repairs,
        private readonly AssetLifecycleService $lifecycle,
        private readonly ApprovalService $approvals,
        private readonly Actor $actor,
    ) {}

    // ---- Warranties ----

    public function warranties(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(Warranty::query()->with([self::ASSET, 'asset.item:id,name_en', 'provider:id,name']), $request,
            filters: [
                'asset_id' => 'asset_id',
                'status' => 'status',
                'expiring' => fn ($q, $v) => $v === 'true' ? $q->where('status', 'active')->whereBetween('ends_on', [now()->toDateString(), now()->addDays(60)->toDateString()]) : $q,
            ],
            sorts: ['id', 'ends_on', 'starts_on'],
            search: ['component', 'coverage_type'],
            defaultSort: 'ends_on',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function storeWarranty(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required'],
            'component' => ['nullable', 'string', 'max:120'],
            'provider_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'coverage_type' => ['nullable', 'string', 'max:40'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->lifecycle->addWarranty($data)), 'Warranty recorded.');
    }

    public function extendWarranty(Request $request, Warranty $warranty): JsonResponse
    {
        $data = $request->validate([
            'ends_on' => ['required', 'date'],
            'provider_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'coverage_type' => ['nullable', 'string', 'max:40'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->lifecycle->extendWarranty($warranty, $data)), 'Extension recorded as a new linked warranty; the original is kept.');
    }

    // ---- Repairs ----

    public function repairs(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(RepairCase::query()->with([self::ASSET, 'asset.item:id,name_en', 'provider:id,name', 'reportedBy:id,full_name']), $request,
            filters: ['status' => 'status', 'asset_id' => 'asset_id', 'under_warranty' => 'under_warranty', 'open' => fn ($q, $v) => $v === 'true' ? $q->where('status', '!=', 'closed') : $q],
            sorts: ['id', 'case_no', 'created_at'],
            search: ['case_no', 'fault_description'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function showRepair(RepairCase $repairCase): JsonResponse
    {
        $data = (new MasterRecordResource($repairCase->load([self::ASSET, 'asset.item:id,name_en', 'provider', 'warranty', 'reportedBy:id,full_name', 'updates.officer:id,full_name'])))->resolve(request());
        $data['approvals'] = $this->approvals->history($repairCase);

        return ApiResponse::success($data);
    }

    public function storeRepair(Request $request): JsonResponse
    {
        $data = $request->validate(['asset_id' => ['required'], 'fault_description' => ['required', 'string', 'min:5', 'max:2000']]);
        $case = $this->repairs->create($data);

        return ApiResponse::created(new MasterRecordResource($case->load(self::ASSET)), "Repair case {$case->case_no} opened".($case->under_warranty ? ' (under warranty).' : '.'));
    }

    public function repairAction(Request $request, RepairCase $repairCase, string $action): JsonResponse
    {
        $case = match ($action) {
            'assess' => $this->repairs->assess($repairCase, $request->validate([
                'assessment' => ['required', 'string', 'max:2000'],
                'provider_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
                'quotation_amount' => ['nullable', 'numeric', 'min:0'],
                'expected_return_on' => ['nullable', 'date'],
            ])),
            'approve' => $this->repairs->approve($repairCase, $request->validate([
                'remarks' => ['nullable', 'string', 'max:255'],
                'expenditure_approval_ref' => ['nullable', 'string', 'max:190'],
            ])),
            'dispatch' => $this->repairs->dispatch($repairCase, $request->validate([
                'accessories_sent' => ['nullable', 'string', 'max:1000'],
                'expected_return_on' => ['nullable', 'date'],
            ])),
            'return' => $this->repairs->markReturned($repairCase, $request->validate(['total_cost' => ['nullable', 'numeric', 'min:0']])),
            'test' => $this->repairs->test($repairCase, (bool) $request->validate(['passed' => ['required', 'boolean']])['passed'], $request->input('remarks')),
            'close' => $this->repairs->close($repairCase, $request->validate(['outcome' => ['nullable', 'string', 'max:100']])['outcome'] ?? null),
            default => abort(404),
        };

        return ApiResponse::success(new MasterRecordResource($case->load(self::ASSET)), 'Repair case updated.');
    }

    public function addRepairUpdate(Request $request, RepairCase $repairCase): JsonResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'update_source' => ['nullable', Rule::in(['provider', 'officer'])],
            'part_description' => ['nullable', 'string', 'max:190'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->repairs->addUpdate($repairCase, $data)), 'Update added.');
    }

    // ---- Service schedules ----

    public function schedules(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(ServiceSchedule::query()->with([self::ASSET, 'asset.item:id,name_en', 'responsibleOfficer:id,full_name']), $request,
            filters: ['asset_id' => 'asset_id', 'is_active' => 'is_active', 'due' => fn ($q, $v) => $v === 'true' ? $q->where('is_active', true)->whereDate('next_due_on', '<=', now()->addDays(14)) : $q],
            sorts: ['id', 'next_due_on'],
            search: ['service_type'],
            defaultSort: 'next_due_on',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $this->actor->authorize('service.manage');
        $data = $request->validate([
            'asset_id' => ['required'],
            'service_type' => ['required', 'string', 'max:60'],
            'interval_days' => ['nullable', 'integer', 'min:1'],
            'usage_threshold' => ['nullable', 'integer', 'min:1'],
            'last_service_on' => ['nullable', 'date'],
            'next_due_on' => ['nullable', 'date'],
            'responsible_officer_id' => ['nullable', 'integer', Rule::exists('officers', 'id')],
        ]);
        $data['asset_id'] = Find::byKey(Asset::class, $data['asset_id'])->id;
        if (empty($data['next_due_on']) && ! empty($data['interval_days'])) {
            $data['next_due_on'] = now()->parse($data['last_service_on'] ?? now())->addDays($data['interval_days'])->toDateString();
        }

        return ApiResponse::created(new MasterRecordResource(ServiceSchedule::create($data)), 'Service schedule saved.');
    }

    public function recordService(Request $request, ServiceSchedule $serviceSchedule): JsonResponse
    {
        $this->actor->authorize('service.manage');
        $on = $request->validate(['serviced_on' => ['required', 'date', 'before_or_equal:today']])['serviced_on'];
        $serviceSchedule->update([
            'last_service_on' => $on,
            'next_due_on' => $serviceSchedule->interval_days ? now()->parse($on)->addDays($serviceSchedule->interval_days)->toDateString() : null,
        ]);

        return ApiResponse::success(new MasterRecordResource($serviceSchedule), 'Service recorded.');
    }

    // ---- Verification ----

    public function verificationSessions(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(AssetVerificationSession::query()->with('leadOfficer:id,full_name')->withCount('verifications'), $request,
            filters: ['status' => 'status'],
            sorts: ['id', 'conducted_on'],
            search: ['session_no', 'scope_note'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function showVerificationSession(AssetVerificationSession $assetVerificationSession): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($assetVerificationSession->load(['leadOfficer:id,full_name', 'verifications.asset:id,public_id,asset_no', 'verifications.foundLocation:id,name'])));
    }

    public function storeVerificationSession(Request $request): JsonResponse
    {
        $data = $request->validate(['scope_note' => ['nullable', 'string', 'max:255'], 'conducted_on' => ['nullable', 'date']]);

        return ApiResponse::created(new MasterRecordResource($this->lifecycle->openVerification($data)), 'Verification session opened.');
    }

    public function verifyAsset(Request $request, AssetVerificationSession $assetVerificationSession): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required'],
            'found_location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'found_custodian_officer_id' => ['nullable', 'integer', Rule::exists('officers', 'id')],
            'condition_found' => ['nullable', Rule::in(Asset::CONDITIONS)],
            'result' => ['nullable', Rule::in(['match', 'mismatch', 'missing', 'found_unrecorded'])],
            'follow_up' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->lifecycle->verify($assetVerificationSession, $data)), 'Verification recorded.');
    }

    public function closeVerificationSession(AssetVerificationSession $assetVerificationSession): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($this->lifecycle->closeVerification($assetVerificationSession)), 'Session closed.');
    }

    // ---- Valuations ----

    public function valuations(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(Valuation::query()->with([self::ASSET, 'asset.item:id,name_en', 'assessedBy:id,full_name']), $request,
            filters: ['status' => 'status', 'asset_id' => 'asset_id'],
            sorts: ['id', 'effective_on'],
            search: ['decision_ref'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function storeValuation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required'],
            'new_value' => ['required', 'numeric', 'min:0'],
            'effective_on' => ['required', 'date'],
            'decision_ref' => ['required', 'string', 'max:190'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->lifecycle->proposeValuation($data)), 'Valuation submitted for approval.');
    }

    public function decideValuation(Request $request, Valuation $valuation): JsonResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'remarks' => ['nullable', 'string', 'max:255']]);

        return ApiResponse::success(new MasterRecordResource($this->lifecycle->decideValuation($valuation, $data['approve'], $data['remarks'] ?? null)), 'Valuation decided.');
    }

    // ---- Disposals ----

    public function disposals(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(Disposal::query()->with([self::ASSET, 'asset.item:id,name_en', 'proposedBy:id,full_name']), $request,
            filters: ['status' => 'status', 'method' => 'method', 'asset_id' => 'asset_id'],
            sorts: ['id', 'disposed_on', 'created_at'],
            search: ['decision_ref', 'reason'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function storeDisposal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required'],
            'method' => ['required', Rule::in(Disposal::METHODS)],
            'decision_ref' => ['required', 'string', 'max:190'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->lifecycle->proposeDisposal($data)), 'Disposal proposed for approval.');
    }

    public function decideDisposal(Request $request, Disposal $disposal): JsonResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'remarks' => ['nullable', 'string', 'max:255']]);

        return ApiResponse::success(new MasterRecordResource($this->lifecycle->decideDisposal($disposal, $data['approve'], $data['remarks'] ?? null)), 'Disposal decided.');
    }

    public function completeDisposal(Request $request, Disposal $disposal): JsonResponse
    {
        $data = $request->validate(['disposed_on' => ['nullable', 'date'], 'outcome' => ['nullable', 'string', 'max:255']]);

        return ApiResponse::success(new MasterRecordResource($this->lifecycle->completeDisposal($disposal, $data)), 'Asset disposed.');
    }
}
