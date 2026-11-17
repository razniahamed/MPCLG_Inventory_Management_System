<?php

namespace App\Http\Requests\Organisation;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class StoreOfficersRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'officers' => ['present', 'array'],
            'officers.*.officer_id' => ['required', 'integer', Rule::exists('officers', 'id')->whereNull('deleted_at')],
            'officers.*.role' => ['nullable', 'string', 'max:30'],
            'officers.*.valid_from' => ['nullable', 'date'],
            'officers.*.valid_to' => ['nullable', 'date'],
            'reason' => $this->reasonRule(),
        ];
    }
}
