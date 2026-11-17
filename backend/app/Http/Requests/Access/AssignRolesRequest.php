<?php

namespace App\Http\Requests\Access;

use App\Enums\ScopeType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class AssignRolesRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['replace', 'merge'])],
            'roles' => ['present', 'array'],
            'roles.*.role' => ['required', 'string', 'exists:roles,code'],
            'roles.*.scope_type' => ['nullable', Rule::in(ScopeType::values())],
            'roles.*.scope_id' => ['nullable', 'integer'],
            'roles.*.valid_from' => ['nullable', 'date'],
            'roles.*.valid_to' => ['nullable', 'date', 'after:now'],
            'reason' => $this->reasonRule(required: true),
        ];
    }
}
