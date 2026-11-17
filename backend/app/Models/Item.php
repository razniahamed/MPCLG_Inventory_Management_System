<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use IsMasterData;

    public const TRACKING = ['quantity', 'asset'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
            'has_batch' => 'boolean',
            'has_expiry' => 'boolean',
            'is_paper' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function issueUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'issue_unit_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ItemUnitConversion::class);
    }

    public function isAsset(): bool
    {
        return $this->tracking_method === 'asset';
    }
}
