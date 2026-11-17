<?php

namespace App\Http\Requests\Access;

use App\Http\Requests\ApiRequest;

class SyncRolePermissionsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct'],
            'reason' => $this->reasonRule(required: true),
        ];
    }
}
