<?php

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Officer;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = $this->admin();
    $this->actingAsUser($this->admin);
});

it('creates a pending user with a one-time temporary password and initial roles', function () {
    $officer = Officer::factory()->create();

    $response = $this->postJson('/api/v1/users', [
        'username' => 'k.perera',
        'email' => 'k.perera@mpclg.local',
        'officer_id' => $officer->id,
        'roles' => [['role' => 'staff']],
        'reason' => 'New staff member',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.username', 'k.perera')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.must_change_password', true)
        ->assertJsonPath('data.roles.0.role.code', 'staff');

    $temporary = $response->json('data.temporary_password');
    expect($temporary)->toBeString()->toHaveLength(14);

    $user = User::where('username', 'k.perera')->first();
    expect(Hash::check($temporary, $user->password))->toBeTrue();
    expect(AuditLog::where('auditable_type', 'users')->where('auditable_id', $user->id)->where('action', 'created')->exists())->toBeTrue();

    // The temporary password works and forces a change.
    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['username' => 'k.perera', 'password' => $temporary])
        ->assertOk()->assertJsonPath('data.must_change_password', true);
});

it('validates duplicate usernames, emails and officer links', function () {
    $existing = User::factory()->create(['officer_id' => Officer::factory()->create()->id]);

    $this->assertApiError($this->postJson('/api/v1/users', [
        'username' => $existing->username,
        'email' => $existing->email,
        'officer_id' => $existing->officer_id,
    ]), 422, 'VALIDATION_ERROR')->assertJsonStructure(['errors' => ['username', 'email', 'officer_id']]);
});

it('lists, filters and shows users by public id', function () {
    User::factory()->count(3)->create();
    User::factory()->suspended()->create(['username' => 'suspended.one']);

    $this->getJson('/api/v1/users?filter[status]=suspended')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.username', 'suspended.one');

    $this->getJson('/api/v1/users?per_page=2&sort=-created_at')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);

    $this->getJson('/api/v1/users/'.$this->admin->public_id)->assertOk()->assertJsonPath('data.id', $this->admin->public_id);
    $this->assertApiError($this->getJson('/api/v1/users/'.$this->admin->id), 404, 'NOT_FOUND');
    $this->assertApiError($this->getJson('/api/v1/users?filter[password]=x'), 422, 'VALIDATION_ERROR');
});

it('updates user details but never the password', function () {
    $user = User::factory()->create();
    $hash = $user->password;

    $this->patchJson("/api/v1/users/{$user->public_id}", ['email' => 'changed@mpclg.local', 'password' => 'Hacked#1234567'])
        ->assertOk()
        ->assertJsonPath('data.email', 'changed@mpclg.local');

    expect($user->fresh()->password)->toBe($hash);
});

it('rejects a deactivated user\'s token immediately', function () {
    $user = $this->userWithRoles(['staff']);
    $token = $this->loginToken($user);
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

    // Deactivate (as admin) while the user still holds the token.
    $user->forceFill(['status' => UserStatus::Deactivated])->save();

    $this->assertApiError($this->withToken($token)->getJson('/api/v1/auth/me'), 403, 'ACCOUNT_DISABLED');
});

it('deactivates a user: revokes tokens, ends roles and overrides, keeps history', function () {
    $user = $this->userWithRoles(['storekeeper']);
    $this->loginToken($user);
    expect($user->tokens()->count())->toBe(1);

    $this->app['auth']->forgetGuards();
    $this->actingAsUser($this->admin);

    $this->postJson("/api/v1/users/{$user->public_id}/deactivate", ['reason' => 'Retired from service'])
        ->assertOk()
        ->assertJsonPath('data.status', 'deactivated')
        ->assertJsonPath('data.deactivation_reason', 'Retired from service');

    $user->refresh();
    expect($user->tokens()->count())->toBe(0)
        ->and($user->deactivated_by)->toBe($this->admin->id)
        ->and(UserRole::where('user_id', $user->id)->open()->count())->toBe(0)
        ->and(UserRole::where('user_id', $user->id)->count())->toBe(1); // history kept

    // Accounts are never deleted.
    expect(User::find($user->id))->not->toBeNull();

    $this->assertApiError(
        $this->postJson("/api/v1/users/{$user->public_id}/reactivate", ['reason' => 'oops']),
        422, 'BUSINESS_RULE_VIOLATION',
    );
});

it('requires a reason for status changes', function () {
    $user = User::factory()->create();

    $this->assertApiError($this->postJson("/api/v1/users/{$user->public_id}/suspend"), 422, 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['reason']]);
});

it('suspends and reactivates a user, revoking sessions on suspend', function () {
    $user = $this->userWithRoles(['staff']);
    $token = $this->loginToken($user);

    $this->app['auth']->forgetGuards();
    $this->actingAsUser($this->admin);

    $this->postJson("/api/v1/users/{$user->public_id}/suspend", ['reason' => 'Under investigation'])
        ->assertOk()->assertJsonPath('data.status', 'suspended');
    expect($user->tokens()->count())->toBe(0);

    $this->postJson("/api/v1/users/{$user->public_id}/reactivate", ['reason' => 'Cleared'])
        ->assertOk()->assertJsonPath('data.status', 'active');
});

it('returns 403 ACCOUNT_DISABLED for a suspended user who still has a valid session', function () {
    $user = $this->userWithRoles(['staff']);
    $user->forceFill(['status' => UserStatus::Suspended])->save();

    $this->actingAsUser($user);
    $this->assertApiError($this->getJson('/api/v1/auth/me'), 403, 'ACCOUNT_DISABLED');
});

it('prevents administrators from suspending or deactivating themselves', function () {
    $this->assertApiError(
        $this->postJson("/api/v1/users/{$this->admin->public_id}/deactivate", ['reason' => 'test']),
        422, 'BUSINESS_RULE_VIOLATION',
    );
});

it('protects the last active administrator from deactivation', function () {
    // An HR officer may deactivate users but is not an administrator.
    $hr = $this->userWithRoles(['staff']);
    $this->overridePermission($hr, 'user.deactivate', 'allow');

    $this->actingAsUser($hr);
    $this->assertApiError(
        $this->postJson("/api/v1/users/{$this->admin->public_id}/deactivate", ['reason' => 'test']),
        422, 'BUSINESS_RULE_VIOLATION',
    )->assertJsonPath('message', fn ($m) => str_contains($m, 'no active administrator'));

    expect($this->admin->fresh()->status)->toBe(UserStatus::Active);

    // With a second administrator in place the deactivation is allowed.
    $this->admin();
    $this->postJson("/api/v1/users/{$this->admin->public_id}/deactivate", ['reason' => 'Left the ministry'])->assertOk();
});

it('resets a password to a temporary one and forces a change', function () {
    $user = $this->userWithRoles(['staff']);

    $response = $this->postJson("/api/v1/users/{$user->public_id}/reset-password", ['reason' => 'Forgot password'])->assertOk();

    expect($response->json('data.temporary_password'))->toBeString();
    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('force-logs-out every session', function () {
    $user = $this->userWithRoles(['staff']);
    $this->loginToken($user);
    $this->loginToken($user);

    $this->app['auth']->forgetGuards();
    $this->actingAsUser($this->admin);

    $this->postJson("/api/v1/users/{$user->public_id}/revoke-tokens", ['reason' => 'Lost laptop'])
        ->assertOk()->assertJsonPath('data.tokens_revoked', 2);
});

it('denies user management to roles without the permission', function () {
    $this->actingAsUser($this->userWithRoles(['storekeeper']));

    $this->assertApiError($this->getJson('/api/v1/users'), 403, 'PERMISSION_DENIED');
    $this->assertApiError($this->postJson('/api/v1/users', []), 403, 'PERMISSION_DENIED');
});
