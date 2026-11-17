<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreUserRequest;
use App\Http\Requests\Access\UpdateUserRequest;
use App\Http\Requests\ReasonRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Access\UserService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with(['officer.designation', 'officer.department']);

        $page = ListQuery::paginate($query, $request,
            filters: [
                'status' => 'status',
                'officer_id' => 'officer_id',
                'department_id' => fn ($q, $v) => $q->whereHas('officer', fn ($o) => $o->where('department_id', $v)),
                'role' => fn ($q, $v) => $q->whereHas('roleAssignments', fn ($a) => $a->open()->whereHas('role', fn ($r) => $r->where('code', $v))),
            ],
            sorts: ['id', 'username', 'email', 'status', 'created_at', 'last_login_at'],
            search: ['username', 'email'],
            defaultSort: 'username',
        );

        return ApiResponse::paginated($page, UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $result = $this->users->create($request->validated());
        $user = $result['user']->load(['officer', 'roleAssignments' => fn ($q) => $q->open()->with('role')]);

        $data = (new UserResource($user))->resolve($request);
        if ($result['temporary_password']) {
            // Shown ONCE so the administrator can hand it over; never stored in clear.
            $data['temporary_password'] = $result['temporary_password'];
        }

        return ApiResponse::created($data, 'User created.');
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource(
            $user->load(['officer.designation', 'officer.department', 'roleAssignments' => fn ($q) => $q->open()->with('role')])
        ));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->users->update($user, $request->validated())), 'User updated.');
    }

    public function suspend(ReasonRequest $request, User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->users->suspend($user, $request->validated('reason'))), 'User suspended.');
    }

    public function reactivate(ReasonRequest $request, User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->users->reactivate($user, $request->validated('reason'))), 'User reactivated.');
    }

    public function deactivate(ReasonRequest $request, User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->users->deactivate($user, $request->validated('reason'))), 'User deactivated.');
    }

    public function resetPassword(ReasonRequest $request, User $user): JsonResponse
    {
        $temporary = $this->users->resetPassword($user, $request->validated('reason'));

        return ApiResponse::success([
            'temporary_password' => $temporary,
            'must_change_password' => true,
        ], 'Password reset. Give the temporary password to the user; it must be changed at next sign-in.');
    }

    public function revokeTokens(ReasonRequest $request, User $user): JsonResponse
    {
        $count = $this->users->revokeTokens($user, $request->validated('reason'));

        return ApiResponse::success(['tokens_revoked' => $count], 'All sessions signed out.');
    }
}
