<?php

namespace App\Services\Access;

use App\Enums\AuditAction;
use App\Enums\AuthEventType;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\AuthEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\System\SettingsService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Login, logout and password flows with lockout and auth_events (Section 6).
 */
class AuthService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{user: User, token: string, expires_at: Carbon}
     */
    public function login(string $login, string $password, ?string $deviceName, ?string $ip, ?string $userAgent): array
    {
        $user = User::query()
            ->where('username', $login)
            ->orWhere('email', $login)
            ->first();

        if (! $user) {
            $this->event(null, AuthEventType::LoginFailed, $ip, $userAgent, $login);
            throw new ApiException('Invalid username or password.', 'UNAUTHENTICATED', 401);
        }

        if ($user->isLocked()) {
            $this->event($user, AuthEventType::LoginFailed, $ip, $userAgent, $login);
            throw ApiException::accountLocked($user->locked_until->toIso8601String());
        }

        if (! Hash::check($password, $user->password)) {
            $this->registerFailure($user, $ip, $userAgent, $login);
        }

        if (! $user->status->canSignIn()) {
            $this->event($user, AuthEventType::LoginFailed, $ip, $userAgent, $login);
            throw ApiException::accountDisabled();
        }

        return DB::transaction(function () use ($user, $deviceName, $ip, $userAgent) {
            $user->forceFill([
                'failed_login_count' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
                'last_login_ip' => $ip,
            ])->save();

            $expiresAt = now()->addMinutes($this->settings->int('auth.token_ttl_minutes'));
            $newToken = $user->createToken($deviceName ?: 'api', ['*'], $expiresAt);
            $newToken->accessToken->forceFill([
                'device_name' => $deviceName ? mb_substr($deviceName, 0, 100) : null,
                'ip_address' => $ip,
            ])->save();

            $this->event($user, AuthEventType::LoginSuccess, $ip, $userAgent);

            return ['user' => $user, 'token' => $newToken->plainTextToken, 'expires_at' => $expiresAt];
        });
    }

    public function logout(User $user, ?string $ip, ?string $userAgent): void
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->event($user, AuthEventType::Logout, $ip, $userAgent);
    }

    public function changePassword(User $user, string $current, string $new, ?string $ip, ?string $userAgent): void
    {
        if (! Hash::check($current, $user->password)) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        if (Hash::check($new, $user->password)) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                'password' => ['The new password must be different from the current password.'],
            ]);
        }

        DB::transaction(function () use ($user, $new, $ip, $userAgent) {
            $wasPending = $user->status === UserStatus::Pending;

            $user->forceFill([
                'password' => $new,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'status' => $wasPending ? UserStatus::Active : $user->status,
            ])->save();

            // Keep the current session, sign out every other device.
            $currentId = $user->currentAccessToken()?->id;
            $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

            if ($wasPending) {
                $user->bumpPermissionVersion();
                $this->audit->log(AuditAction::StatusChanged, $user, ['status' => 'pending'], ['status' => 'active'], 'First password change activated the account.');
            }

            $this->event($user, AuthEventType::PasswordChanged, $ip, $userAgent);
        });
    }

    /** Always succeeds from the caller's view, so accounts cannot be enumerated. */
    public function sendResetLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        if ($user && $user->status->canSignIn()) {
            Password::broker()->sendResetLink(['email' => $email]);
        }
    }

    public function resetPassword(string $email, string $token, string $password, ?string $ip, ?string $userAgent): void
    {
        $status = Password::broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password) use ($ip, $userAgent) {
                if (! $user->status->canSignIn()) {
                    throw ApiException::accountDisabled();
                }

                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'failed_login_count' => 0,
                    'locked_until' => null,
                    'status' => $user->status === UserStatus::Pending ? UserStatus::Active : $user->status,
                ])->save();

                $user->tokens()->delete();
                $this->event($user, AuthEventType::PasswordReset, $ip, $userAgent);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                'token' => ['This password reset token is invalid or has expired.'],
            ]);
        }
    }

    public function event(?User $user, AuthEventType $type, ?string $ip, ?string $userAgent, ?string $usernameTried = null): void
    {
        AuthEvent::create([
            'user_id' => $user?->id,
            'username_tried' => $usernameTried ? mb_substr($usernameTried, 0, 60) : null,
            'event' => $type,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    private function registerFailure(User $user, ?string $ip, ?string $userAgent, string $login): never
    {
        $max = $this->settings->int('auth.max_failed_logins');
        $count = $user->failed_login_count + 1;
        $locked = $count >= $max;

        $user->forceFill([
            'failed_login_count' => $count,
            'locked_until' => $locked ? now()->addMinutes($this->settings->int('auth.lockout_minutes')) : $user->locked_until,
        ])->save();

        $this->event($user, AuthEventType::LoginFailed, $ip, $userAgent, $login);

        if ($locked) {
            $user->forceFill(['failed_login_count' => 0])->save();
            $this->event($user, AuthEventType::Locked, $ip, $userAgent, $login);
            throw ApiException::accountLocked($user->locked_until->toIso8601String());
        }

        throw new ApiException('Invalid username or password.', 'UNAUTHENTICATED', 401);
    }
}
