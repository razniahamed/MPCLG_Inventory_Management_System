<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\DepartmentReturn;
use App\Models\Discrepancy;
use App\Services\Access\Actor;
use App\Services\Requests\DepartmentReturnService;
use App\Support\ApiResponse;
use App\Support\Idempotency;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentReturnController extends Controller
{
    use ScopesListings;

    private const WITH = ['department:id,code,name_en', 'store:id,code,name', 'originalIssue:id,public_id,issue_no'];

    public function __construct(private readonly DepartmentReturnService $returns, private readonly Actor $actor) {}

    public function index(Request $request): JsonResponse
    {
        $query = DepartmentReturn::query()->with(self::WITH);
        if (! $this->actor->can('return.inspect')) {
            $this->limitToDepartments($query, 'return.create');
        } else {
            $this->limitToStores($query, 'return.inspect');
        }

        $page = ListQuery::paginate($query, $request,
            filters: ['status' => 'status', 'department_id' => 'department_id', 'store_id' => 'store_id'],
            sorts: ['id', 'return_no', 'return_on'],
            search: ['return_no', 'reason'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(DepartmentReturn $departmentReturn): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($departmentReturn->load([...self::WITH, 'lines.item:id,item_code,name_en'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'issue_id' => ['required', 'integer', Rule::exists('issues', 'id')],
            'return_on' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.issue_line_id' => ['required', 'integer'],
            'lines.*.qty_returned' => ['required', 'numeric', 'gt:0'],
            'lines.*.condition' => ['nullable', 'string', 'max:30'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::created(new MasterRecordResource($this->returns->create($data)->load(self::WITH)), 'Return submitted to Stores.');
    }

    public function inspect(Request $request, DepartmentReturn $departmentReturn): JsonResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.qty_accepted' => ['required', 'numeric', 'min:0'],
            'lines.*.condition' => ['nullable', 'string', 'max:30'],
        ]);

        return ApiResponse::success(new MasterRecordResource($this->returns->inspect($departmentReturn, $data['lines'])), 'Inspection recorded.');
    }

    public function post(Request $request, DepartmentReturn $departmentReturn): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($this->returns->post($departmentReturn, Idempotency::keyFrom($request))), 'Accepted goods returned to stock.');
    }

    // ---- Discrepancies (receipt shortfalls, refused transfers, count variances) ----

    public function discrepancies(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(Discrepancy::query()->with('resolvedBy:id,full_name'), $request,
            filters: ['status' => 'status', 'discrepancy_type' => 'discrepancy_type'],
            sorts: ['id', 'created_at'],
            search: ['description', 'resolution'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function resolveDiscrepancy(Request $request, Discrepancy $discrepancy): JsonResponse
    {
        $data = $request->validate(['resolution' => ['required', 'string', 'min:3', 'max:255']]);
        abort_if($discrepancy->status === 'resolved', 422, 'Already resolved.');

        $discrepancy->update([
            'status' => 'resolved',
            'resolution' => $data['resolution'],
            'resolved_by_officer_id' => $this->actor->officerId(),
            'resolved_at' => now(),
        ]);

        return ApiResponse::success(new MasterRecordResource($discrepancy), 'Discrepancy resolved.');
    }
}
