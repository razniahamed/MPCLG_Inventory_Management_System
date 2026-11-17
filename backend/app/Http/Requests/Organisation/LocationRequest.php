<?php

namespace App\Http\Requests\Organisation;

use App\Enums\LocationType;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends ApiRequest
{
    public function rules(): array
    {
        $r = $this->onCreate();
        $location = $this->route('location');
        $siteId = $this->input('site_id', $location?->site_id);

        return [
            'site_id' => [$r, 'integer', Rule::exists('sites', 'id')->whereNull('deleted_at')],
            'store_id' => ['sometimes', 'nullable', 'integer', Rule::exists('stores', 'id')->whereNull('deleted_at')],
            'parent_location_id' => ['sometimes', 'nullable', 'integer', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'location_type' => [$r, Rule::in(LocationType::values())],
            'code' => [$r, 'string', 'max:40', Rule::unique('locations', 'code')->where('site_id', $siteId)->ignore($location)],
            'name' => [$r, 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'reason' => $this->reasonRule(),
        ];
    }
}
