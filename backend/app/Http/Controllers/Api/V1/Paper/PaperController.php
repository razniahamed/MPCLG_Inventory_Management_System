<?php

namespace App\Http\Controllers\Api\V1\Paper;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\A4Exception;
use App\Models\Machine;
use App\Models\MeterObservation;
use App\Models\PaperStatement;
use App\Models\SharedUsageAllocation;
use App\Services\Paper\MeterService;
use App\Services\Paper\PaperService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A4 paper control endpoints (Phase 5). */
class PaperController extends Controller
{
    use ScopesListings;

    public function __construct(private readonly MeterService $meters, private readonly PaperService $paper) {}

    // ---- Machines ----

    public function machines(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(Machine::query()->with(['responsibleDepartment:id,code,name_en', 'location:id,name', 'counters', 'asset:id,public_id,asset_no']), $request,
            filters: ['is_active' => 'is_active', 'responsible_department_id' => 'responsible_department_id', 'is_shared' => 'is_shared'],
            sorts: ['id', 'code', 'model'],
            search: ['code', 'model', 'leased_reference'],
            defaultSort: 'code',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function showMachine(Machine $machine): JsonResponse
    {
        $machine->load(['responsibleDepartment', 'location', 'counters', 'asset', 'departmentAssignments.department']);
        $data = (new MasterRecordResource($machine))->resolve(request());
        $data['recent_observations'] = MasterRecordResource::collection(
            $machine->observations()->with(['readings.counter', 'observedBy:id,full_name'])->orderByDesc('observed_at')->limit(10)->get()
        )->resolve(request());

        return ApiResponse::success($data);
    }

    public function storeMachine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('machines', 'code')],
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id'), Rule::unique('machines', 'asset_id')],
            'leased_reference' => ['nullable', 'required_without:asset_id', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:120'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'responsible_department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'is_shared' => ['boolean'],
            'counter_types' => ['nullable', 'array', 'min:1'],
            'counter_types.*' => [Rule::in(Machine::COUNTER_TYPES)],
            'shared_department_ids' => ['nullable', 'array'],
            'shared_department_ids.*' => ['integer', Rule::exists('departments', 'id')],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->meters->createMachine($data)), 'Machine registered.');
    }

    // ---- Meter readings ----

    public function observations(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(MeterObservation::query()->with(['machine:id,code,model', 'readings.counter', 'observedBy:id,full_name']), $request,
            filters: ['status' => 'status', 'machine_id' => 'machine_id', 'is_baseline' => 'is_baseline'],
            sorts: ['id', 'observed_at'],
            search: ['remarks'],
            defaultSort: '-observed_at',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function storeObservation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'id')],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'is_baseline' => ['nullable', 'boolean'],
            'baseline_reason' => ['nullable', Rule::in(['first_reading', 'reset', 'replacement'])],
            'remarks' => ['nullable', 'string', 'max:255'],
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.counter_id' => ['required', 'integer'],
            'readings.*.reading_value' => ['nullable', 'integer', 'min:0'],
            'readings.*.is_unknown' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'file', 'image', 'max:8192'],
        ]);

        $observation = $this->meters->record($data, $request->file('photo'));
        $flags = $observation->flag_reasons ?? [];

        return ApiResponse::created(new MasterRecordResource($observation), $flags ? 'Reading saved but flagged: '.implode(', ', $flags).'.' : 'Reading saved.');
    }

    public function verifyObservation(Request $request, MeterObservation $meterObservation): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'flag'])], 'remarks' => ['nullable', 'string', 'max:255']]);

        return ApiResponse::success(new MasterRecordResource($this->meters->verify($meterObservation, $data['decision'], $data['remarks'] ?? null)), 'Reading verified.');
    }

    // ---- Shared usage ----

    public function allocations(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(SharedUsageAllocation::query()->with(['machine:id,code', 'lines.department:id,code,name_en']), $request,
            filters: ['status' => 'status', 'machine_id' => 'machine_id'],
            sorts: ['id', 'period_from_on'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function storeAllocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'id')],
            'period_from_on' => ['required', 'date'],
            'period_to_on' => ['required', 'date', 'after_or_equal:period_from_on'],
            'counter_type' => ['nullable', Rule::in(Machine::COUNTER_TYPES)],
            'total_usage' => ['nullable', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'lines.*.allocated_usage' => ['required', 'integer', 'min:0'],
            'lines.*.basis' => ['nullable', Rule::in(['measured', 'declared'])],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->paper->allocate($data)), 'Usage allocated.');
    }

    public function reviewAllocation(Request $request, SharedUsageAllocation $sharedUsageAllocation): JsonResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'remarks' => ['nullable', 'string', 'max:255']]);

        return ApiResponse::success(new MasterRecordResource($this->paper->reviewAllocation($sharedUsageAllocation, $data['approve'], $data['remarks'] ?? null)), 'Allocation reviewed.');
    }

    // ---- Paper statements ----

    public function statements(Request $request): JsonResponse
    {
        $query = $this->limitToDepartments(PaperStatement::query()->with(['department:id,code,name_en', 'item:id,item_code,name_en']), 'paper_statement.manage');

        $page = ListQuery::paginate($query, $request,
            filters: ['status' => 'status', 'department_id' => 'department_id'],
            sorts: ['id', 'period_from_on'],
            defaultSort: '-period_from_on',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function storeStatement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'period_from_on' => ['required', 'date'],
            'period_to_on' => ['required', 'date', 'after_or_equal:period_from_on'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'receipts' => ['nullable', 'numeric', 'min:0'],
            'transfers' => ['nullable', 'numeric'],
            'returns' => ['nullable', 'numeric', 'min:0'],
            'usage_or_loss' => ['nullable', 'numeric', 'min:0'],
            'counted_closing' => ['nullable', 'numeric', 'min:0'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'is_baseline' => ['nullable', 'boolean'],
        ]);

        $statement = $this->paper->createStatement($data);
        $resource = (new MasterRecordResource($statement->load(['department', 'item'])))->resolve($request);
        $resource['expected_closing'] = $statement->expectedClosing();
        $resource['unexplained_difference'] = $statement->unexplainedDifference();

        return ApiResponse::created($resource, 'Paper statement submitted.');
    }

    public function certifyStatement(Request $request, PaperStatement $paperStatement): JsonResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:255']])['remarks'] ?? null;

        return ApiResponse::success(new MasterRecordResource($this->paper->certifyStatement($paperStatement, $remarks)), 'Statement certified.');
    }

    // ---- A4 exceptions ----

    public function exceptions(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(A4Exception::query()->with('request:id,public_id,request_no,department_name_snapshot'), $request,
            filters: ['status' => 'status', 'exception_type' => 'exception_type', 'request_id' => 'request_id'],
            sorts: ['id', 'created_at'],
            search: ['explanation'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function storeException(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'integer', Rule::exists('requests', 'id')],
            'exception_type' => ['required', Rule::in(A4Exception::TYPES)],
            'explanation' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->paper->raiseException($data)), 'Exception raised for approval.');
    }

    public function decideException(Request $request, A4Exception $a4Exception): JsonResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'remarks' => ['nullable', 'string', 'max:255']]);

        return ApiResponse::success(new MasterRecordResource($this->paper->decideException($a4Exception, $data['approve'], $data['remarks'] ?? null)), 'Exception decided.');
    }
}
