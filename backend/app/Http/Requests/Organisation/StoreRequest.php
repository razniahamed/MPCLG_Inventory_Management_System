<?php

namespace App\Http\Requests\Organisation;

use App\Enums\StoreType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

/** Validates a Store (stores room) record — not "store a record". */
class StoreRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();

        return [
            'code' => [$r, 'string', 'max:30', Rule::unique('stores', 'code')->ignore($this->route('store'))],
            'name' => [$r, 'string', 'max:150'],
            'site_id' => [$r, 'integer', Rule::exists('sites', 'id')->whereNull('deleted_at')],
            'store_type' => ['sometimes', 'nullable', Rule::in(StoreType::values())],
            'responsible_officer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('officers', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
