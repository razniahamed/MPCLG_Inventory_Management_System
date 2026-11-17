<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Store;
use App\Models\UserPermission;
use App\Services\Access\PermissionResolver;
use App\Services\Access\Scope;

beforeEach(function () {
    $this->resolver = app(PermissionResolver::class);
});

it('grants permissions through roles', function () {
    $user = $this->userWithRoles(['storekeeper']);

    expect($this->resolver->can($user, 'issue.post'))->toBeTrue()
        ->and($this->resolver->can($user, 'user.create'))->toBeFalse();
});

it('lets an explicit deny override beat a role allow', function () {
    $user = $this->userWithRoles(['storekeeper']);
    $this->overridePermission($user, 'issue.post', 'deny');

    expect($this->resolver->can($user->fresh(), 'issue.post'))->toBeFalse()
        ->and($this->resolver->explain($user->fresh(), 'issue.post')['reason'])->toBe('denied_by_override');
});

it('lets an allow override grant a permission no role gives', function () {
    $user = $this->userWithRoles(['staff']);
    $this->overridePermission($user, 'stock.view', 'allow');

    expect($this->resolver->can($user->fresh(), 'stock.view'))->toBeTrue()
        ->and($this->resolver->explain($user->fresh(), 'stock.view')['reason'])->toBe('allowed_by_override');
});

it('ignores expired and not-yet-started overrides', function () {
    $user = $this->userWithRoles(['storekeeper']);
    $this->overridePermission($user, 'issue.post', 'deny', ['valid_from' => now()->subDays(10), 'valid_to' => now()->subDay()]);
    $this->overridePermission($user, 'user.view', 'allow', ['valid_from' => now()->addDay()]);

    $user = $user->fresh();
    expect($this->resolver->can($user, 'issue.post'))->toBeTrue()
        ->and($this->resolver->can($user, 'user.view'))->toBeFalse();

    $this->travel(2)->days();
    expect($this->resolver->can($user, 'user.view'))->toBeTrue();
});

it('ignores revoked overrides and expired role assignments', function () {
    $user = $this->userWithRoles(['storekeeper']);
    $deny = $this->overridePermission($user, 'issue.post', 'deny');
    $deny->update(['revoked_at' => now()]);
    $user->bumpPermissionVersion();

    expect($this->resolver->can($user->fresh(), 'issue.post'))->toBeTrue();

    $user->roleAssignments()->update(['valid_to' => now()->subSecond()]);
    $user->bumpPermissionVersion();
    expect($this->resolver->can($user->fresh(), 'issue.post'))->toBeFalse();
});

it('applies scope: department, division and store', function () {
    $division = Division::factory()->create();
    $deptA = Department::factory()->create(['division_id' => $division->id]);
    $deptB = Department::factory()->create();
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $clerk = $this->userWithRoles([['chief_clerk', 'department', $deptA->id]]);
    expect($this->resolver->can($clerk, 'request.create', Scope::department($deptA->id)))->toBeTrue()
        ->and($this->resolver->can($clerk, 'request.create', Scope::department($deptB->id)))->toBeFalse()
        ->and($this->resolver->can($clerk, 'request.create'))->toBeTrue(); // "somewhere"

    $head = $this->userWithRoles([['department_head', 'division', $division->id]]);
    expect($this->resolver->can($head, 'request.certify', Scope::department($deptA->id)))->toBeTrue()
        ->and($this->resolver->can($head, 'request.certify', Scope::department($deptB->id)))->toBeFalse();

    $keeper = $this->userWithRoles([['storekeeper', 'store', $storeA->id]]);
    expect($this->resolver->can($keeper, 'issue.post', Scope::store($storeA->id)))->toBeTrue()
        ->and($this->resolver->can($keeper, 'issue.post', Scope::store($storeB->id)))->toBeFalse();
});

it('lets a scoped deny remove one scope while others remain', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $keeper = $this->userWithRoles([['storekeeper', 'store', $storeA->id], ['storekeeper', 'store', $storeB->id]]);
    $this->overridePermission($keeper, 'issue.post', 'deny', ['scope_type' => 'store', 'scope_id' => $storeA->id]);

    $keeper = $keeper->fresh();
    expect($this->resolver->can($keeper, 'issue.post', Scope::store($storeA->id)))->toBeFalse()
        ->and($this->resolver->can($keeper, 'issue.post', Scope::store($storeB->id)))->toBeTrue()
        ->and($this->resolver->can($keeper, 'issue.post'))->toBeTrue();
});

it('denies everything to inactive or locked users', function () {
    $user = $this->userWithRoles(['storekeeper']);
    $user->forceFill(['locked_until' => now()->addMinutes(5)])->save();

    expect($this->resolver->can($user, 'stock.view'))->toBeFalse()
        ->and($this->resolver->explain($user, 'stock.view')['reason'])->toBe('account_locked');
});

it('never gives System Administrators approval authority (no super-admin bypass)', function () {
    $admin = $this->admin();

    foreach (['request.approve', 'grn.approve_post', 'adjustment.approve', 'transfer.approve', 'disposal.approve', 'stock.view'] as $code) {
        expect($this->resolver->can($admin, $code))->toBeFalse("system_admin must not hold {$code}");
    }

    expect($this->resolver->can($admin, 'user.create'))->toBeTrue()
        ->and($this->resolver->can($admin, 'department.manage'))->toBeTrue();
});

it('refreshes the cached permission set when permission_version is bumped', function () {
    $user = $this->userWithRoles(['staff']);
    expect($this->resolver->can($user, 'stock.view'))->toBeFalse(); // now cached

    // A change written without bumping the version is not seen (cache hit) ...
    UserPermission::create([
        'user_id' => $user->id, 'permission_id' => Permission::where('code', 'stock.view')->value('id'),
        'effect' => 'allow', 'scope_type' => 'global', 'valid_from' => now()->subMinute(), 'reason' => 'test',
    ]);
    expect(app(PermissionResolver::class)->can($user->fresh(), 'stock.view'))->toBeFalse();

    // ... bumping the version makes it visible immediately.
    $user->bumpPermissionVersion();
    expect(app(PermissionResolver::class)->can($user->fresh(), 'stock.view'))->toBeTrue();
});

it('refreshes access immediately when an admin changes roles through the API', function () {
    $user = $this->userWithRoles(['staff']);
    $this->actingAsUser($user);
    $this->assertApiError($this->getJson('/api/v1/departments'), 403, 'PERMISSION_DENIED');

    $this->actingAsUser($this->admin());
    $this->putJson("/api/v1/users/{$user->public_id}/roles", [
        'mode' => 'merge', 'roles' => [['role' => 'chief_clerk']], 'reason' => 'Promoted to coordinator',
    ])->assertOk();

    $this->actingAsUser($user->fresh());
    $this->getJson('/api/v1/departments')->assertOk();
});
