<?php

namespace App\Providers;

use App\Services\System\SettingsService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Login / password-reset endpoints (Section 12).
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(30)->by('ip:'.$request->ip()),
                // Account lockout (auth.max_failed_logins) is the main brute-force control.
                Limit::perMinute(10)->by('login:'.strtolower((string) $request->input('username', $request->input('email')))),
            ];
        });

        // Reset links point at the frontend SPA, which calls POST /api/v1/auth/reset-password.
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('mpclg.frontend_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($user->email));

        Password::defaults(function () {
            $min = rescue(fn () => app(SettingsService::class)->int('auth.password_min_length'), 10, false);

            return Password::min(max(8, $min))->letters()->mixedCase()->numbers()->symbols();
        });
    }
}
