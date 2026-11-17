<?php

namespace App\Http\Requests\Organisation;

use App\Enums\DesignationCategory;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class DesignationRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();

        return [
            'code' => [$r, 'string', 'max:30', Rule::unique('designations', 'code')->ignore($this->route('designation'))],
            'name' => [$r, 'string', 'max:120'],
            'category' => ['sometimes', 'nullable', Rule::in(DesignationCategory::values())],
            'is_head_post' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
