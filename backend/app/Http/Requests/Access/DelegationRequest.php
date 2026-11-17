<?php

namespace App\Http\Requests\Access;

use App\Enums\ScopeType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class DelegationRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'delegator_user_id' => ['required', 'string', 'exists:users,public_id'],
                'delegate_user_id' => ['required', 'string', 'exists:users,public_id', 'different:delegator_user_id'],
                'permission_code' => ['nullable', 'string', 'exists:permissions,code'],
                'scope' => ['nullable', 'array'],
                'scope.scope_type' => ['required_with:scope', Rule::in(ScopeType::values())],
                'scope.scope_id' => ['nullable', 'integer'],
                'valid_from' => ['required', 'date'],
                'valid_to' => ['required', 'date', 'after:valid_from', 'after:now'],
                'written_reference' => ['required', 'string', 'min:3', 'max:190'],
                'attachment_id' => ['nullable', 'integer', 'exists:attachments,id'],
                'reason' => $this->reasonRule(),
            ];
        }

        return [
            'valid_to' => ['sometimes', 'date', 'after:now'],
            'written_reference' => ['sometimes', 'string', 'min:3', 'max:190'],
            'reason' => $this->reasonRule(required: true),
        ];
    }
}
