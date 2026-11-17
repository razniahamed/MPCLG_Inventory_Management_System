<?php

namespace App\Http\Requests\Catalogue;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();
        $unit = Rule::exists('units', 'id')->whereNull('deleted_at');

        return [
            'item_code' => [$r, 'string', 'max:40', 'regex:/^[A-Za-z0-9._\/-]+$/', Rule::unique('items', 'item_code')->ignore($this->route('item'))],
            'legacy_code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'register_book_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'register_page_no' => ['sometimes', 'nullable', 'string', 'max:30'],
            'name_en' => [$r, 'string', 'max:190'],
            'name_si' => ['sometimes', 'nullable', 'string', 'max:190'],
            'name_ta' => ['sometimes', 'nullable', 'string', 'max:190'],
            'category_id' => [$r, 'integer', Rule::exists('item_categories', 'id')->whereNull('deleted_at')],
            'specification' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tracking_method' => [$r, Rule::in(['quantity', 'asset'])],
            'base_unit_id' => [$r, 'integer', $unit],
            'purchase_unit_id' => [$r, 'integer', $unit],
            'issue_unit_id' => [$r, 'integer', $unit],
            'reorder_level' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'reorder_qty' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'has_batch' => ['sometimes', 'boolean'],
            'has_expiry' => ['sometimes', 'boolean'],
            'is_paper' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
