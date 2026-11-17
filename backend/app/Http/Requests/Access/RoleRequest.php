<?php

namespace App\Http\Requests\Access;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends ApiRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')],
                'name' => ['required', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:2000'],
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['string'],
                'reason' => $this->reasonRule(),
            ];
        }

        // The code is permanent once created.
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
