<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request. Authorisation is done by the `permission:` route middleware
 * and services; requests only validate input.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** 'required' on create (POST), 'sometimes' on partial update (PATCH/PUT). */
    protected function onCreate(): string
    {
        return $this->isMethod('POST') ? 'required' : 'sometimes';
    }

    /** Optional free-text reason recorded in the audit trail. */
    protected function reasonRule(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'min:3', 'max:255'];
    }
}
