<?php

namespace App\Http\Requests\Access;

use App\Enums\ScopeType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class SetUserPermissionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'effect' => ['required', Rule::in(['allow', 'deny', 'inherit'])],
            'scope' => ['nullable', 'array'],
            'scope.scope_type' => ['required_with:scope', Rule::in(ScopeType::values())],
            'scope.scope_id' => ['nullable', 'integer'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after:now'],
            'reason' => $this->reasonRule(required: true),
        ];
    }
}
