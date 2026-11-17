<?php

namespace App\Http\Requests\Organisation;

use App\Enums\SiteType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();

        return [
            'code' => [$r, 'string', 'max:30', Rule::unique('sites', 'code')->ignore($this->route('site'))],
            'name' => [$r, 'string', 'max:150'],
            'site_type' => [$r, Rule::in(SiteType::values())],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
