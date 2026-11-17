<?php

namespace App\Http\Requests\Organisation;

use App\Enums\EmploymentStatus;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class OfficerRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();
        $live = fn (string $table) => Rule::exists($table, 'id')->whereNull('deleted_at');

        // No NIC or other personal data unless Finance/HR formally requires it.
        return [
            'officer_no' => [$r, 'string', 'max:30', Rule::unique('officers', 'officer_no')->ignore($this->route('officer'))],
            'full_name' => [$r, 'string', 'max:190'],
            'name_with_initials' => ['sometimes', 'nullable', 'string', 'max:150'],
            'designation_id' => [$r, 'integer', $live('designations')],
            'department_id' => [$r, 'integer', $live('departments')],
            'public_service_id' => ['sometimes', 'nullable', 'integer', $live('public_services')],
            'service_grade_id' => ['sometimes', 'nullable', 'integer', $live('service_grades')],
            'work_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'work_email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'employment_status' => ['sometimes', Rule::in(EmploymentStatus::values())],
            'joined_on' => ['sometimes', 'nullable', 'date'],
            'left_on' => ['sometimes', 'nullable', 'date'],
            'authority_ref' => ['sometimes', 'nullable', 'string', 'max:190'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
