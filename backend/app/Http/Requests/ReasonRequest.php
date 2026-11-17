<?php

namespace App\Http\Requests;

/**
 * For actions that need a mandatory reason (status changes, revocations).
 */
class ReasonRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reason' => $this->reasonRule(required: true),
        ];
    }
}
