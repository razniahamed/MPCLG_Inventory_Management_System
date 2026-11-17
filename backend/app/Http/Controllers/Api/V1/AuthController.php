<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Module;
use App\Services\Access\AuthService;
use App\Services\Access\PermissionResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * Sign in with username (or email) and password. Returns a bearer token.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('username'),
            $request->validated('password'),
            $request->validated('device_name'),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(),
            'must_change_password' => $result['user']->must_change_password,
            'user' => (new UserResource($result['user']))->resolve($request),
        ], 'Signed in.');
    }

    /** Revoke the current token. */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user(), $request->ip(), $request->userAgent());

        return ApiResponse::success(null, 'Signed out.');
    }

    /** Profile, effective permissions and enabled modules (for showing / hiding features). */
    public function me(Request $request, PermissionResolver $resolver): JsonResponse
    {
        $user = $request->user()->load(['officer.designation', 'officer.department', 'roleAssignments' => fn ($q) => $q->open()->with('role')]);
        $effective = $resolver->effectivePermissions($user);

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve($request),
            'permissions' => array_keys($effective),
            'permission_scopes' => $effective,
            'modules' => Module::orderBy('sort_order')->get(['code', 'name', 'is_enabled']),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(null, 'Password changed.');
    }

    /** @unauthenticated */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->sendResetLink($request->validated('email'));

        return ApiResponse::success(null, 'If the address belongs to an active account, a reset link has been sent.');
    }

    /** @unauthenticated */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(null, 'Password reset. Please sign in.');
    }
}
