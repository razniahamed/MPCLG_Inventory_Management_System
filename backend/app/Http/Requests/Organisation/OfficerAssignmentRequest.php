<?php

namespace App\Http\Requests\Organisation;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class OfficerAssignmentRequest extends ApiRequest
{
    public function rules(): array
    {
        $live = fn (string $table) => Rule::exists($table, 'id')->whereNull('deleted_at');

        return [
            'designation_id' => ['required', 'integer', $live('designations')],
            'department_id' => ['required', 'integer', $live('departments')],
            'public_service_id' => ['nullable', 'integer', $live('public_services')],
            'service_grade_id' => ['nullable', 'integer', $live('service_grades')],
            'from_on' => ['required', 'date'],
            'authority_ref' => ['nullable', 'string', 'max:190'],
            'reason' => $this->reasonRule(),
        ];
    }
}
