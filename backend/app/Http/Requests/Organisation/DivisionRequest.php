<?php

namespace App\Http\Requests\Organisation;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class DivisionRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();

        return [
            'code' => [$r, 'string', 'max:30', Rule::unique('divisions', 'code')->ignore($this->route('division'))],
            'name_en' => [$r, 'string', 'max:150'],
            'name_si' => ['sometimes', 'nullable', 'string', 'max:150'],
            'name_ta' => ['sometimes', 'nullable', 'string', 'max:150'],
            'head_designation_id' => ['sometimes', 'nullable', 'integer', Rule::exists('designations', 'id')->whereNull('deleted_at')],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
