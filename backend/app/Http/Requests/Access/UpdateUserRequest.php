<?php

namespace App\Http\Requests\Access;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends ApiRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        // The password is never changed here (Section 6).
        return [
            'username' => ['sometimes', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($userId)],
            'officer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('officers', 'id')->whereNull('deleted_at'), Rule::unique('users', 'officer_id')->ignore($userId)],
            'reason' => $this->reasonRule(),
        ];
    }
}
