<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [master] tables: audit columns + is_active + soft delete.
 */
trait IsMasterData
{
    use HasAuditColumns, SoftDeletes;

    public function initializeIsMasterData(): void
    {
        $this->mergeCasts(['is_active' => 'boolean']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->getTable().'.is_active', true);
    }
}
