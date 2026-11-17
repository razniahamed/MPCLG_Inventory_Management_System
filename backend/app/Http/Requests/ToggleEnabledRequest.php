<?php

namespace App\Http\Requests;

class ToggleEnabledRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'reason' => $this->reasonRule(required: true),
        ];
    }
}
