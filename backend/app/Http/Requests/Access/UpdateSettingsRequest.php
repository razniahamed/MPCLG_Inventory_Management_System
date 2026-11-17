<?php

namespace App\Http\Requests\Access;

use App\Enums\SettingValueType;
use App\Http\Requests\ApiRequest;
use App\Services\System\SettingsService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Body: { "settings": [ { "key": "auth.max_failed_logins", "value": 6 } ], "reason": "..." }
 */
class UpdateSettingsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'distinct', Rule::in(array_keys(SettingsService::DEFINITIONS))],
            'settings.*.value' => ['present'],
            'reason' => $this->reasonRule(required: true),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('settings', []) as $i => $entry) {
                    $definition = SettingsService::DEFINITIONS[$entry['key'] ?? ''] ?? null;
                    if (! $definition) {
                        continue;
                    }

                    $value = $entry['value'] ?? null;
                    $ok = match ($definition[1]) {
                        SettingValueType::Integer => filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= 0,
                        SettingValueType::Boolean => is_bool($value) || in_array($value, [0, 1, '0', '1'], true),
                        SettingValueType::String => is_string($value) && $value !== '' && mb_strlen($value) <= 500,
                        SettingValueType::Json => is_array($value),
                    };

                    if ($ok && $entry['key'] === 'a4.rule_mode') {
                        $ok = in_array($value, ['per_request', 'monthly'], true);
                    }

                    if (! $ok) {
                        $validator->errors()->add("settings.{$i}.value", "Invalid value for {$entry['key']} (expected {$definition[1]->value}).");
                    }
                }
            },
        ];
    }

    /** @return array<string, mixed> key => value */
    public function settingValues(): array
    {
        return collect($this->validated('settings'))->mapWithKeys(fn ($e) => [$e['key'] => $e['value']])->all();
    }
}
