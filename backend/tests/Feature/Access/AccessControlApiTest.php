<?php

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Access\PermissionResolver;

beforeEach(function () {
    $this->admin = $this->admin();
    $this->actingAsUser($this->admin);
});

it('turns one permission off and on again for one user', function () {
    $keeper = $this->userWithRoles(['storekeeper']);
    $url = "/api/v1/users/{$keeper->public_id}/permissions/issue.post";

    $this->putJson($url, ['effect' => 'deny', 'reason' => 'Pending investigation'])
        ->assertOk()
        ->assertJsonPath('data.effect', 'deny')
        ->assertJsonPath('data.permission', 'issue.post');

    expect(app(PermissionResolver::class)->can($keeper->fresh(), 'issue.post'))->toBeFalse();

    $this->putJson($url, ['effect' => 'inherit', 'reason' => 'Investigation closed'])
        ->assertOk()
        ->assertJsonPath('data', null);

    expect(app(PermissionResolver::class)->can($keeper->fresh(), 'issue.post'))->toBeTrue();

    // History is kept: the deny row is revoked, not deleted.
    expect(UserPermission::where('user_id', $keeper->id)->whereNotNull('revoked_at')->count())->toBe(1);
    expect(AuditLog::where('auditable_id', $keeper->id)->where('action', 'permission_changed')->count())->toBe(2);
});

it('supports scoped, time-limited overrides and rejects scope on global-only permissions', function () {
    $user = $this->userWithRoles(['staff']);
    $dept = Department::factory()->create();

    $this->putJson("/api/v1/users/{$user->public_id}/permissions/request.create", [
        'effect' => 'allow',
        'scope' => ['scope_type' => 'department', 'scope_id' => $dept->id],
        'valid_to' => now()->addWeek()->toIso8601String(),
        'reason' => 'Acting coordinator',
    ])->assertOk()->assertJsonPath('data.scope_type', 'department');

    $this->assertApiError($this->putJson("/api/v1/users/{$user->public_id}/permissions/user.view", [
        'effect' => 'allow',
        'scope' => ['scope_type' => 'department', 'scope_id' => $dept->id],
        'reason' => 'test',
    ]), 422, 'BUSINESS_RULE_VIOLATION');

    $this->assertApiError($this->putJson("/api/v1/users/{$user->public_id}/permissions/request.create", [
        'effect' => 'allow',
        'scope' => ['scope_type' => 'department', 'scope_id' => 999999],
        'reason' => 'test',
    ]), 422, 'VALIDATION_ERROR');

    $this->getJson("/api/v1/users/{$user->public_id}/permissions")->assertOk()->assertJsonCount(1, 'data');
});

it('shows why a user can or cannot do something', function () {
    $keeper = $this->userWithRoles(['storekeeper']);
    $this->overridePermission($keeper, 'issue.post', 'deny');

    $rows = collect($this->getJson("/api/v1/users/{$keeper->public_id}/effective-permissions")->assertOk()->json('data.permissions'))->keyBy('code');

    expect($rows['issue.create'])->allowed->toBeTrue()->reason->toBe('allowed_by_role')
        ->and($rows['issue.create']['sources'][0]['label'])->toBe('storekeeper')
        ->and($rows['issue.post'])->allowed->toBeFalse()->reason->toBe('denied_by_override')
        ->and($rows['user.view'])->allowed->toBeFalse()->reason->toBe('not_granted');
});

it('stops an admin from removing their own last administrative permission', function () {
    $this->assertApiError(
        $this->putJson("/api/v1/users/{$this->admin->public_id}/permissions/role.manage", ['effect' => 'deny', 'reason' => 'oops']),
        422, 'BUSINESS_RULE_VIOLATION',
    );

    $this->assertApiError(
        $this->putJson("/api/v1/users/{$this->admin->public_id}/roles", ['mode' => 'replace', 'roles' => [], 'reason' => 'oops']),
        422, 'BUSINESS_RULE_VIOLATION',
    );

    expect(app(PermissionResolver::class)->can($this->admin->fresh(), 'role.manage'))->toBeTrue();
});

it('replaces and merges role assignments with scope and validity', function () {
    $user = $this->userWithRoles(['staff']);
    $dept = Department::factory()->create();

    $this->putJson("/api/v1/users/{$user->public_id}/roles", [
        'mode' => 'merge',
        'roles' => [['role' => 'chief_clerk', 'scope_type' => 'department', 'scope_id' => $dept->id]],
        'reason' => 'Coordinator',
    ])->assertOk()->assertJsonCount(2, 'data');

    $this->putJson("/api/v1/users/{$user->public_id}/roles", [
        'mode' => 'replace',
        'roles' => [['role' => 'chief_clerk', 'scope_type' => 'department', 'scope_id' => $dept->id]],
        'reason' => 'Coordinator only',
    ])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.role.code', 'chief_clerk');

    $this->getJson("/api/v1/users/{$user->public_id}/roles?history=1")->assertOk()->assertJsonCount(2, 'data');

    $this->assertApiError($this->putJson("/api/v1/users/{$user->public_id}/roles", [
        'mode' => 'merge', 'roles' => [['role' => 'chief_clerk', 'scope_type' => 'department']], 'reason' => 'test',
    ]), 422, 'VALIDATION_ERROR');
});

it('blocks every endpoint of a disabled module and re-enables it', function () {
    $hr = $this->userWithRoles(['system_admin']);
    $module = Module::where('code', 'organisation')->first();

    $this->patchJson("/api/v1/modules/{$module->id}/enabled", ['is_enabled' => false, 'reason' => 'Phased go-live'])
        ->assertOk()->assertJsonPath('data.is_enabled', false);

    $this->actingAsUser($hr);
    $this->assertApiError($this->getJson('/api/v1/departments'), 403, 'PERMISSION_DENIED')
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'switched off'));
    $this->getJson('/api/v1/users')->assertOk(); // other modules unaffected

    $this->patchJson("/api/v1/modules/{$module->id}/enabled", ['is_enabled' => true, 'reason' => 'Go-live'])->assertOk();
    $this->getJson('/api/v1/departments')->assertOk();
});

it('refuses to switch off the access module or lock-out permissions', function () {
    $access = Module::where('code', 'access')->first();
    $this->assertApiError($this->patchJson("/api/v1/modules/{$access->id}/enabled", ['is_enabled' => false, 'reason' => 'test']), 422, 'BUSINESS_RULE_VIOLATION');

    $roleManage = Permission::where('code', 'role.manage')->first();
    $this->assertApiError($this->patchJson("/api/v1/permissions/{$roleManage->id}/enabled", ['is_enabled' => false, 'reason' => 'test']), 422, 'BUSINESS_RULE_VIOLATION');
});

it('switches one permission off globally for everyone', function () {
    $permission = Permission::where('code', 'user.view')->first();

    $this->patchJson("/api/v1/permissions/{$permission->id}/enabled", ['is_enabled' => false, 'reason' => 'Audit freeze'])
        ->assertOk()->assertJsonPath('data.is_enabled', false);

    $this->assertApiError($this->getJson('/api/v1/users'), 403, 'PERMISSION_DENIED');
    expect(app(PermissionResolver::class)->can($this->admin, 'user.view'))->toBeFalse();
});

it('lists the permission catalogue grouped by module', function () {
    $data = $this->getJson('/api/v1/permissions')->assertOk()->json('data');

    expect($data)->toHaveCount(10)
        ->and(collect($data)->firstWhere('code', 'access')['permissions'])->not->toBeEmpty();
});

it('manages roles and their permission sets', function () {
    $created = $this->postJson('/api/v1/roles', [
        'code' => 'stores_viewer',
        'name' => 'Stores Viewer',
        'permissions' => ['stock.view', 'item.view'],
        'reason' => 'Read-only stores role',
    ])->assertCreated()->assertJsonPath('data.permissions', ['item.view', 'stock.view']);

    $roleId = $created->json('data.id');
    $user = $this->userWithRoles(['stores_viewer']);
    expect(app(PermissionResolver::class)->can($user, 'ledger.view'))->toBeFalse();

    $this->putJson("/api/v1/roles/{$roleId}/permissions", ['permissions' => ['stock.view', 'item.view', 'ledger.view'], 'reason' => 'Add ledger'])
        ->assertOk()->assertJsonPath('data.permissions', ['item.view', 'ledger.view', 'stock.view']);

    // Holders see the change immediately.
    expect(app(PermissionResolver::class)->can($user->fresh(), 'ledger.view'))->toBeTrue();

    $this->assertApiError($this->deleteJson("/api/v1/roles/{$roleId}", ['reason' => 'test']), 422, 'BUSINESS_RULE_VIOLATION');

    $user->roleAssignments()->update(['is_active' => false, 'revoked_at' => now()]);
    $this->deleteJson("/api/v1/roles/{$roleId}", ['reason' => 'No longer needed'])->assertOk();
    expect(Role::withTrashed()->find($roleId)->trashed())->toBeTrue();
});

it('protects seeded roles and keeps approvals away from admin and audit roles', function () {
    $systemAdmin = Role::where('code', 'system_admin')->first();
    $auditor = Role::where('code', 'internal_auditor')->first();

    $this->assertApiError($this->deleteJson("/api/v1/roles/{$systemAdmin->id}", ['reason' => 'test']), 422, 'BUSINESS_RULE_VIOLATION');

    $this->assertApiError($this->putJson("/api/v1/roles/{$auditor->id}/permissions", [
        'permissions' => ['audit.view', 'request.approve'],
        'reason' => 'test',
    ]), 422, 'BUSINESS_RULE_VIOLATION')->assertJsonPath('message', fn ($m) => str_contains($m, 'request.approve'));

    $this->assertApiError($this->putJson("/api/v1/roles/{$systemAdmin->id}/permissions", [
        'permissions' => ['nope.code'],
        'reason' => 'test',
    ]), 422, 'VALIDATION_ERROR');
});

it('records delegations that give approval authority for a period and revokes them', function () {
    $accountant = $this->userWithRoles(['accountant_supply']);
    $deputy = $this->userWithRoles(['staff']);

    $response = $this->postJson('/api/v1/delegations', [
        'delegator_user_id' => $accountant->public_id,
        'delegate_user_id' => $deputy->public_id,
        'permission_code' => 'request.approve',
        'valid_from' => now()->subMinute()->toIso8601String(),
        'valid_to' => now()->addDays(7)->toIso8601String(),
        'written_reference' => 'MPCLG/ACC/2026/114',
    ])->assertCreated()->assertJsonPath('data.status', 'active');

    $resolver = app(PermissionResolver::class);
    expect($resolver->can($deputy->fresh(), 'request.approve'))->toBeTrue()
        ->and($resolver->explain($deputy->fresh(), 'request.approve')['reason'])->toBe('allowed_by_delegation')
        ->and($resolver->can($deputy->fresh(), 'grn.approve_post'))->toBeFalse();

    // Expires on its own after valid_to.
    $this->travel(8)->days();
    expect(app(PermissionResolver::class)->can($deputy->fresh(), 'request.approve'))->toBeFalse();
    $this->travelBack();

    $this->deleteJson('/api/v1/delegations/'.$response->json('data.id'), ['reason' => 'Accountant back'])
        ->assertOk()->assertJsonPath('data.status', 'revoked');
    expect(app(PermissionResolver::class)->can($deputy->fresh(), 'request.approve'))->toBeFalse();
});

it('validates delegations: written reference, approval permissions only, no admin delegates', function () {
    $accountant = $this->userWithRoles(['accountant_supply']);
    $deputy = $this->userWithRoles(['staff']);
    $base = [
        'delegator_user_id' => $accountant->public_id,
        'delegate_user_id' => $deputy->public_id,
        'valid_from' => now()->toIso8601String(),
        'valid_to' => now()->addDay()->toIso8601String(),
        'written_reference' => 'REF/1',
    ];

    $this->assertApiError($this->postJson('/api/v1/delegations', array_merge($base, ['written_reference' => null])), 422, 'VALIDATION_ERROR');
    $this->assertApiError($this->postJson('/api/v1/delegations', array_merge($base, ['permission_code' => 'item.view'])), 422, 'BUSINESS_RULE_VIOLATION');
    $this->assertApiError($this->postJson('/api/v1/delegations', array_merge($base, ['permission_code' => 'disposal.approve'])), 422, 'BUSINESS_RULE_VIOLATION');
    $this->assertApiError($this->postJson('/api/v1/delegations', array_merge($base, ['delegate_user_id' => $this->admin->public_id])), 422, 'BUSINESS_RULE_VIOLATION');
});

it('lets an auditor read the audit trail but not change access', function () {
    $this->postJson('/api/v1/users', ['username' => 'audited.user', 'email' => 'audited@mpclg.local', 'reason' => 'test']);

    $auditor = $this->userWithRoles(['internal_auditor']);
    $this->actingAsUser($auditor);

    $this->getJson('/api/v1/audit-logs?filter[auditable_type]=users&filter[action]=created')
        ->assertOk()
        ->assertJsonPath('data.0.new_values.username', 'audited.user')
        ->assertJsonPath('data.0.user.username', $this->admin->username);

    $this->assertApiError($this->postJson('/api/v1/users', []), 403, 'PERMISSION_DENIED');
    $this->getJson('/api/v1/roles')->assertOk();
});

it('reads and updates system settings with validation', function () {
    $this->getJson('/api/v1/settings')->assertOk()->assertJsonFragment(['key' => 'auth.max_failed_logins', 'value' => 5]);

    $this->patchJson('/api/v1/settings', [
        'settings' => [['key' => 'auth.max_failed_logins', 'value' => 3]],
        'reason' => 'Tighter policy',
    ])->assertOk()->assertJsonFragment(['key' => 'auth.max_failed_logins', 'value' => 3]);

    $this->assertApiError($this->patchJson('/api/v1/settings', [
        'settings' => [['key' => 'auth.max_failed_logins', 'value' => 'lots']],
        'reason' => 'test',
    ]), 422, 'VALIDATION_ERROR');

    $this->assertApiError($this->patchJson('/api/v1/settings', [
        'settings' => [['key' => 'no.such.key', 'value' => 1]],
        'reason' => 'test',
    ]), 422, 'VALIDATION_ERROR');

    // The new limit applies to logins.
    $user = User::factory()->create();
    $this->app['auth']->forgetGuards();
    foreach (range(1, 2) as $i) {
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'bad'])->assertStatus(401);
    }
    $this->assertApiError($this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'bad']), 423, 'ACCOUNT_LOCKED');
});
