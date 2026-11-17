<?php

use App\Enums\UserStatus;
use App\Models\AuthEvent;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('signs in with username or email and returns an expiring token', function () {
    $user = $this->userWithRoles(['staff']);

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => $user->username,
        'password' => UserFactory::PASSWORD,
        'device_name' => 'Chrome on Windows',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->public_id)
        ->assertJsonPath('data.must_change_password', false);

    expect($response->json('data.expires_at'))->not->toBeNull();
    expect($user->tokens()->first())
        ->device_name->toBe('Chrome on Windows')
        ->expires_at->not->toBeNull();

    $this->postJson('/api/v1/auth/login', ['username' => $user->email, 'password' => UserFactory::PASSWORD])->assertOk();

    expect(AuthEvent::where('user_id', $user->id)->where('event', 'login_success')->count())->toBe(2);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password and records the failed attempt', function () {
    $user = $this->userWithRoles(['staff']);

    $this->assertApiError(
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'wrong']),
        401, 'UNAUTHENTICATED',
    );

    expect($user->fresh()->failed_login_count)->toBe(1);
    expect(AuthEvent::where('user_id', $user->id)->where('event', 'login_failed')->exists())->toBeTrue();
});

it('records attempts for unknown usernames without revealing which part was wrong', function () {
    $this->assertApiError(
        $this->postJson('/api/v1/auth/login', ['username' => 'ghost', 'password' => 'x']),
        401, 'UNAUTHENTICATED',
    )->assertJsonPath('message', 'Invalid username or password.');

    expect(AuthEvent::where('username_tried', 'ghost')->exists())->toBeTrue();
});

it('locks the account after the configured number of failures', function () {
    $user = $this->userWithRoles(['staff']);

    foreach (range(1, 4) as $i) {
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'wrong'])->assertStatus(401);
    }

    $this->assertApiError(
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'wrong']),
        423, 'ACCOUNT_LOCKED',
    );

    // Even the right password is refused while locked.
    $this->assertApiError(
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => UserFactory::PASSWORD]),
        423, 'ACCOUNT_LOCKED',
    );

    expect($user->fresh()->isLocked())->toBeTrue();
    expect(AuthEvent::where('user_id', $user->id)->where('event', 'locked')->exists())->toBeTrue();

    $this->travel(16)->minutes();
    $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => UserFactory::PASSWORD])->assertOk();
});

it('refuses sign-in for suspended and deactivated accounts', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->assertApiError(
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => UserFactory::PASSWORD]),
        403, 'ACCOUNT_DISABLED',
    );
})->with(['suspended', 'deactivated']);

it('forces a pending user to change the temporary password before anything else', function () {
    $user = $this->userWithRoles(['system_admin'], ['status' => UserStatus::Pending, 'must_change_password' => true, 'password_changed_at' => null]);
    $token = $this->loginToken($user);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    $this->assertApiError($this->withToken($token)->getJson('/api/v1/users'), 403, 'PASSWORD_CHANGE_REQUIRED');

    $this->withToken($token)->postJson('/api/v1/auth/change-password', [
        'current_password' => UserFactory::PASSWORD,
        'password' => 'N3w-Secure#Pass',
        'password_confirmation' => 'N3w-Secure#Pass',
    ])->assertOk();

    expect($user->fresh())
        ->status->toBe(UserStatus::Active)
        ->must_change_password->toBeFalse();

    $this->withToken($token)->getJson('/api/v1/users')->assertOk();
});

it('enforces the password policy and the current password on change', function () {
    $user = $this->userWithRoles(['staff']);
    $this->actingAsUser($user);

    $this->assertApiError($this->postJson('/api/v1/auth/change-password', [
        'current_password' => UserFactory::PASSWORD,
        'password' => 'short',
        'password_confirmation' => 'short',
    ]), 422, 'VALIDATION_ERROR')->assertJsonStructure(['errors' => ['password']]);

    $this->assertApiError($this->postJson('/api/v1/auth/change-password', [
        'current_password' => 'not-my-password',
        'password' => 'N3w-Secure#Pass',
        'password_confirmation' => 'N3w-Secure#Pass',
    ]), 422, 'VALIDATION_ERROR')->assertJsonStructure(['errors' => ['current_password']]);
});

it('revokes the token on logout', function () {
    $user = $this->userWithRoles(['staff']);
    $token = $this->loginToken($user);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
    $this->assertApiError($this->withToken($token)->getJson('/api/v1/auth/me'), 401, 'UNAUTHENTICATED');
});

it('returns the profile with effective permissions and modules on /auth/me', function () {
    $user = $this->userWithRoles(['staff']);
    $this->actingAsUser($user);

    $response = $this->getJson('/api/v1/auth/me')->assertOk();

    expect($response->json('data.permissions'))->toEqualCanonicalizing(['dashboard.view', 'request.view_own', 'asset.view']);
    expect($response->json('data.modules'))->toHaveCount(10);
    expect($response->json('data.user.roles.0.role.code'))->toBe('staff');
});

it('resets a forgotten password through the emailed token', function () {
    Notification::fake();
    $user = $this->userWithRoles(['staff']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'Br4nd-New#Pass',
        'password_confirmation' => 'Br4nd-New#Pass',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'Br4nd-New#Pass'])->assertOk();
});
