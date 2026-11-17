<?php

namespace App\Http\Requests\Organisation;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class ServiceGradeRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();
        $grade = $this->route('service_grade');
        $serviceId = $this->input('public_service_id', $grade?->public_service_id);

        return [
            'public_service_id' => [$r, 'integer', Rule::exists('public_services', 'id')->whereNull('deleted_at')],
            'code' => [$r, 'string', 'max:20', Rule::unique('service_grades', 'code')->where('public_service_id', $serviceId)->ignore($grade)],
            'rank' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
