<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Exceptions\ApiException;
use App\Http\Requests\Organisation\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends MasterDataController
{
    protected string $model = Department::class;

    protected array $sorts = ['id', 'code', 'name_en', 'active_from', 'created_at'];

    protected array $search = ['code', 'name_en', 'name_si', 'name_ta'];

    protected array $with = ['division', 'parent', 'defaultSite', 'headOfficer', 'coordinatorOfficer'];

    protected array $filters = [
        'division_id' => 'division_id',
        'parent_department_id' => 'parent_department_id',
    ];

    protected string $label = 'Department';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Department $department): JsonResponse
    {
        return $this->showRecord($department);
    }

    public function store(DepartmentRequest $request): JsonResponse
    {
        return $this->createRecord($request, fn (Department $d) => $this->assertNoCycle($d));
    }

    public function update(DepartmentRequest $request, Department $department): JsonResponse
    {
        return $this->updateRecord($request, $department, fn (Department $d) => $this->assertNoCycle($d));
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        return $this->retireRecord($request, $department, [
            'officers' => fn () => $department->officers()->exists(),
            'sub-departments' => fn () => $department->children()->exists(),
        ]);
    }

    /** A department cannot be its own ancestor. */
    private function assertNoCycle(Department $department): void
    {
        $parentId = $department->parent_department_id;
        $seen = [];

        while ($parentId !== null) {
            if ($department->exists && $parentId === $department->id) {
                throw ApiException::businessRule('A department cannot be placed under itself or its own sub-department.');
            }
            if (isset($seen[$parentId])) {
                break;
            }
            $seen[$parentId] = true;
            $parentId = Department::whereKey($parentId)->value('parent_department_id');
        }
    }
}
