<?php

namespace App\Http\Requests\Catalogue;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();

        // No bank details: payment stays with Finance.
        return [
            'code' => [$r, 'string', 'max:30', Rule::unique('suppliers', 'code')->ignore($this->route('supplier'))],
            'name' => [$r, 'string', 'max:190'],
            'supplier_type' => [$r, Rule::in(['goods', 'service', 'both'])],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'tax_ref' => ['sometimes', 'nullable', 'string', 'max:60'],
            'legacy_code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', Rule::exists('item_categories', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
