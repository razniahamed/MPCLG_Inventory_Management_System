<?php

namespace App\Services\Access;

use App\Enums\ScopeType;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

class ScopeValidator
{
    /**
     * Normalise and check a scope coming from a request body.
     */
    public function resolve(?string $type, mixed $id, string $field = 'scope'): Scope
    {
        $scopeType = ScopeType::tryFrom($type ?? ScopeType::Global->value);

        if (! $scopeType) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                "{$field}.scope_type" => ['Scope type must be one of: '.implode(', ', ScopeType::values()).'.'],
            ]);
        }

        if ($scopeType === ScopeType::Global) {
            return Scope::global();
        }

        if (! is_numeric($id) || ! DB::table($scopeType->table())->where('id', (int) $id)->whereNull('deleted_at')->exists()) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, [
                "{$field}.scope_id" => ["A valid {$scopeType->value} id is required for this scope."],
            ]);
        }

        return new Scope($scopeType, (int) $id);
    }
}
