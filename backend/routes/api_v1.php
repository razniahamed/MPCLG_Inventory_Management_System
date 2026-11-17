<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DelegationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\Organisation\DepartmentController;
use App\Http\Controllers\Api\V1\Organisation\DesignationController;
use App\Http\Controllers\Api\V1\Organisation\DivisionController;
use App\Http\Controllers\Api\V1\Organisation\LocationController;
use App\Http\Controllers\Api\V1\Organisation\OfficerController;
use App\Http\Controllers\Api\V1\Organisation\PublicServiceController;
use App\Http\Controllers\Api\V1\Organisation\ServiceGradeController;
use App\Http\Controllers\Api\V1\Organisation\SiteController;
use App\Http\Controllers\Api\V1\Organisation\StoreController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserAccessController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1  (prefix /api/v1 — see bootstrap/app.php)
|--------------------------------------------------------------------------
| Every protected endpoint names exactly one permission code (Section 4).
| Lifecycle: RequestId → ForceJson → auth:sanctum → active → password.changed
|            → permission:<code> → FormRequest → Controller → Service
*/

Route::get('health', HealthController::class)->name('health');

// ---- Public auth (rate-limited) ------------------------------------------
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});

// ---- Signed in (allowed even with a temporary password) --------------------
Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
});

// ---- Everything else ----------------------------------------------------------
Route::middleware(['auth:sanctum', 'active', 'password.changed', 'throttle:api'])->group(function () {

    // Users
    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.view');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.view');
    Route::patch('users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->middleware('permission:user.deactivate');
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('permission:user.deactivate');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->middleware('permission:user.reactivate');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:user.reset_password');
    Route::post('users/{user}/revoke-tokens', [UserController::class, 'revokeTokens'])->middleware('permission:user.revoke_tokens');

    // User access: roles, per-user overrides, effective permissions
    Route::get('users/{user}/effective-permissions', [UserAccessController::class, 'effectivePermissions'])->middleware('permission:permission.view');
    Route::get('users/{user}/roles', [UserAccessController::class, 'roles'])->middleware('permission:permission.assign_role');
    Route::put('users/{user}/roles', [UserAccessController::class, 'assignRoles'])->middleware('permission:permission.assign_role');
    Route::get('users/{user}/permissions', [UserAccessController::class, 'permissions'])->middleware('permission:permission.view');
    Route::put('users/{user}/permissions/{permission:code}', [UserAccessController::class, 'setPermission'])->middleware('permission:permission.override_user')->withoutScopedBindings();

    // Roles
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.manage');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.view');
    Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.manage');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.manage');
    Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:role.manage');

    // Permission catalogue and global switches
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permission.view');
    Route::patch('permissions/{permission}/enabled', [PermissionController::class, 'setEnabled'])->middleware('permission:permission.toggle_global');
    Route::get('modules', [ModuleController::class, 'index'])->middleware('permission:module.toggle');
    Route::patch('modules/{module}/enabled', [ModuleController::class, 'setEnabled'])->middleware('permission:module.toggle');

    // Delegations
    Route::get('delegations', [DelegationController::class, 'index'])->middleware('permission:delegation.manage');
    Route::post('delegations', [DelegationController::class, 'store'])->middleware('permission:delegation.manage');
    Route::get('delegations/{delegation}', [DelegationController::class, 'show'])->middleware('permission:delegation.manage');
    Route::patch('delegations/{delegation}', [DelegationController::class, 'update'])->middleware('permission:delegation.manage');
    Route::delete('delegations/{delegation}', [DelegationController::class, 'destroy'])->middleware('permission:delegation.manage');

    // Audit trail and settings
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
    Route::get('settings', [SettingController::class, 'index'])->middleware('permission:settings.manage');
    Route::patch('settings', [SettingController::class, 'update'])->middleware('permission:settings.manage');

    // Organisation master data: reads need organisation.view (department.view /
    // officer.view for those two), writes need the specific *.manage permission.
    $masterData = [
        'divisions' => [DivisionController::class, 'division', 'organisation.view', 'division.manage'],
        'departments' => [DepartmentController::class, 'department', 'department.view', 'department.manage'],
        'sites' => [SiteController::class, 'site', 'organisation.view', 'site.manage'],
        'stores' => [StoreController::class, 'store', 'organisation.view', 'store.manage'],
        'locations' => [LocationController::class, 'location', 'organisation.view', 'location.manage'],
        'designations' => [DesignationController::class, 'designation', 'organisation.view', 'designation.manage'],
        'public-services' => [PublicServiceController::class, 'public_service', 'organisation.view', 'designation.manage'],
        'service-grades' => [ServiceGradeController::class, 'service_grade', 'organisation.view', 'designation.manage'],
        'officers' => [OfficerController::class, 'officer', 'officer.view', 'officer.manage'],
    ];

    foreach ($masterData as $path => [$controller, $param, $view, $manage]) {
        Route::get($path, [$controller, 'index'])->middleware("permission:{$view}");
        Route::post($path, [$controller, 'store'])->middleware("permission:{$manage}");
        Route::get("{$path}/{{$param}}", [$controller, 'show'])->middleware("permission:{$view}");
        Route::patch("{$path}/{{$param}}", [$controller, 'update'])->middleware("permission:{$manage}");
        Route::delete("{$path}/{{$param}}", [$controller, 'destroy'])->middleware("permission:{$manage}");
    }

    Route::get('stores/{store}/officers', [StoreController::class, 'officers'])->middleware('permission:organisation.view');
    Route::put('stores/{store}/officers', [StoreController::class, 'syncOfficers'])->middleware('permission:store.manage');
    Route::get('officers/{officer}/assignments', [OfficerController::class, 'assignments'])->middleware('permission:officer.view');
    Route::post('officers/{officer}/assignments', [OfficerController::class, 'addAssignment'])->middleware('permission:officer.manage');
});
