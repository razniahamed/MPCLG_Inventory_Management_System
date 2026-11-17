<?php

namespace App\Services\System;

use App\Enums\AuditAction;
use App\Enums\SettingValueType as T;
use App\Exceptions\ApiException;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Typed access to system_settings with code-level defaults.
 */
class SettingsService
{
    private const CACHE_KEY = 'system_settings.all';

    /** key => [group, type, default, description] */
    public const DEFINITIONS = [
        'auth.max_failed_logins' => ['auth', T::Integer, 5, 'Failed logins before the account is locked.'],
        'auth.lockout_minutes' => ['auth', T::Integer, 15, 'How long a locked account stays locked.'],
        'auth.token_ttl_minutes' => ['auth', T::Integer, 480, 'API token lifetime in minutes.'],
        'auth.password_min_length' => ['auth', T::Integer, 10, 'Minimum password length.'],
        'auth.temp_password_length' => ['auth', T::Integer, 14, 'Length of generated temporary passwords.'],
        'auth.mfa_required_for_admins' => ['auth', T::Boolean, false, 'Require TOTP MFA for administrators (Section 16, decision 6).'],
        'org.ministry_name' => ['organisation', T::String, 'Ministry of Provincial Councils and Local Government', 'Official name printed on forms (to confirm, Section 16).'],
        'qr.public_landing' => ['assets', T::Boolean, false, 'QR landing page visible without login (Section 16, decision 8).'],
        'a4.rule_mode' => ['a4_paper', T::String, 'per_request', 'Meter readings per_request or monthly (Section 16, decision 9).'],
        'a4.photo_required' => ['a4_paper', T::Boolean, true, 'Meter photo required with each reading.'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function get(string $key): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return self::DEFINITIONS[$key][2] ?? null;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return SystemSetting::all()->mapWithKeys(fn (SystemSetting $s) => [$s->key => $s->typedValue()])->all();
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values, string $reason): void
    {
        DB::transaction(function () use ($values, $reason) {
            foreach ($values as $key => $value) {
                $setting = SystemSetting::where('key', $key)->lockForUpdate()->first();

                if (! $setting) {
                    $definition = self::DEFINITIONS[$key] ?? null;
                    if (! $definition) {
                        throw ApiException::businessRule("Unknown setting [{$key}].");
                    }
                    $setting = new SystemSetting([
                        'key' => $key,
                        'group_key' => $definition[0],
                        'value_type' => $definition[1],
                        'description' => $definition[3],
                    ]);
                }

                $old = $setting->exists ? $setting->typedValue() : null;
                $setting->value = $setting->value_type->serialize($value);
                $setting->save();

                $this->audit->log(AuditAction::Updated, $setting, ['value' => $old], ['value' => $setting->typedValue()], $reason);
            }
        });

        Cache::forget(self::CACHE_KEY);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
