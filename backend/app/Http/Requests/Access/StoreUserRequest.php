<?php

namespace App\Http\Requests\Access;

use App\Enums\ScopeType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'officer_id' => ['nullable', 'integer', Rule::exists('officers', 'id')->whereNull('deleted_at'), 'unique:users,officer_id'],
            // Omit to generate a temporary password (returned once in the response).
            'password' => ['nullable', 'string', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*.role' => ['required', 'string', 'exists:roles,code'],
            'roles.*.scope_type' => ['nullable', Rule::in(ScopeType::values())],
            'roles.*.scope_id' => ['nullable', 'integer'],
            'roles.*.valid_from' => ['nullable', 'date'],
            'roles.*.valid_to' => ['nullable', 'date', 'after:now'],
            'reason' => $this->reasonRule(),
        ];
    }
}
