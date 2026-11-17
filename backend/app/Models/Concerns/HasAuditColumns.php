<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Fills created_by / updated_by from the authenticated user ([std] columns).
 */
trait HasAuditColumns
{
    public static function bootHasAuditColumns(): void
    {
        static::creating(function ($model) {
            $userId = Auth::id();
            if ($userId !== null) {
                $model->created_by ??= $userId;
                if ($model->getUpdatedAtColumn() !== null) {
                    $model->updated_by ??= $userId;
                }
            }
        });

        static::updating(function ($model) {
            $userId = Auth::id();
            if ($userId !== null) {
                $model->updated_by = $userId;
            }
        });
    }
}
