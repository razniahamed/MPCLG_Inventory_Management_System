<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\OfficerAssignmentRequest;
use App\Http\Requests\Organisation\OfficerRequest;
use App\Http\Resources\OfficerAssignmentResource;
use App\Http\Resources\OfficerResource;
use App\Models\Officer;
use App\Services\Organisation\MasterDataService;
use App\Services\Organisation\OfficerService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficerController extends Controller
{
    private const WITH = ['designation', 'department', 'user'];

    public function __construct(private readonly OfficerService $officers) {}

    public function index(Request $request): JsonResponse
    {
        $page = ListQuery::paginate(Officer::query()->with(self::WITH), $request,
            filters: [
                'is_active' => 'is_active',
                'department_id' => 'department_id',
                'designation_id' => 'designation_id',
                'employment_status' => 'employment_status',
            ],
            sorts: ['id', 'officer_no', 'full_name', 'created_at'],
            search: ['officer_no', 'full_name', 'name_with_initials', 'work_email'],
            defaultSort: 'full_name',
        );

        return ApiResponse::paginated($page, OfficerResource::class);
    }

    public function show(Officer $officer): JsonResponse
    {
        return ApiResponse::success(new OfficerResource($officer->load([...self::WITH, 'assignments.designation', 'assignments.department'])));
    }

    public function store(OfficerRequest $request): JsonResponse
    {
        $officer = $this->officers->create($request->validated());

        return ApiResponse::created(new OfficerResource($officer->load(self::WITH)), 'Officer created.');
    }

    public function update(OfficerRequest $request, Officer $officer): JsonResponse
    {
        $result = $this->officers->update($officer, $request->validated());

        return ApiResponse::success(
            new OfficerResource($result['officer']->load(self::WITH)),
            'Officer updated.',
            meta: $result['warnings'] ? ['warnings' => $result['warnings']] : [],
        );
    }

    public function destroy(Request $request, Officer $officer, MasterDataService $masterData): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];

        $masterData->retire($officer, $reason, [
            'user accounts' => fn () => $officer->user()->whereIn('status', ['active', 'pending', 'suspended'])->exists(),
        ]);

        return ApiResponse::success(null, 'Officer retired.');
    }

    public function assignments(Officer $officer): JsonResponse
    {
        return ApiResponse::success(OfficerAssignmentResource::collection(
            $officer->assignments()->with(['designation', 'department'])->get()
        ));
    }

    /** New post (transfer / promotion). Closes the current assignment. */
    public function addAssignment(OfficerAssignmentRequest $request, Officer $officer): JsonResponse
    {
        $assignment = $this->officers->addAssignment($officer, $request->validated());

        return ApiResponse::created(new OfficerAssignmentResource($assignment->load(['designation', 'department'])), 'Assignment recorded.');
    }
}
