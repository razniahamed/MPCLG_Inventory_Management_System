<?php

namespace App\Http\Requests\Organisation;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class PublicServiceRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();

        return [
            'code' => [$r, 'string', 'max:30', Rule::unique('public_services', 'code')->ignore($this->route('public_service'))],
            'name' => [$r, 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
