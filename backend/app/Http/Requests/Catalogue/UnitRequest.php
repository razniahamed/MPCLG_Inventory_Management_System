<?php

namespace App\Http\Requests\Catalogue;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();

        return [
            'code' => [$r, 'string', 'max:20', Rule::unique('units', 'code')->ignore($this->route('unit'))],
            'name' => [$r, 'string', 'max:60'],
            'kind' => ['sometimes', Rule::in(['count', 'weight', 'volume', 'length', 'pack'])],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
