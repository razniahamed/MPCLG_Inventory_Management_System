<?php

namespace App\Http\Requests\Catalogue;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class ItemCategoryRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();

        return [
            'code' => [$r, 'string', 'max:30', Rule::unique('item_categories', 'code')->ignore($this->route('item_category'))],
            'name' => [$r, 'string', 'max:150'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('item_categories', 'id')->whereNull('deleted_at')],
            'default_tracking_method' => ['sometimes', Rule::in(['quantity', 'asset'])],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
