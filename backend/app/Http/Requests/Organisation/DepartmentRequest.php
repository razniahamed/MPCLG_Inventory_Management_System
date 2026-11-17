<?php

namespace App\Http\Requests\Organisation;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();
        $live = fn (string $table) => Rule::exists($table, 'id')->whereNull('deleted_at');

        return [
            'code' => [$r, 'string', 'max:30', Rule::unique('departments', 'code')->ignore($this->route('department'))],
            'name_en' => [$r, 'string', 'max:150'],
            'name_si' => ['sometimes', 'nullable', 'string', 'max:150'],
            'name_ta' => ['sometimes', 'nullable', 'string', 'max:150'],
            'division_id' => [$r, 'integer', $live('divisions')],
            'parent_department_id' => ['sometimes', 'nullable', 'integer', $live('departments')],
            'head_officer_id' => ['sometimes', 'nullable', 'integer', $live('officers')],
            'coordinator_officer_id' => ['sometimes', 'nullable', 'integer', $live('officers')],
            'default_site_id' => ['sometimes', 'nullable', 'integer', $live('sites')],
            'active_from' => [$r, 'date'],
            'active_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:active_from'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
