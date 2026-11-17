<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    use IsMasterData;

    protected $guarded = ['id'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ItemCategory::class, 'supplier_categories');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'duplicate_of_id');
    }
}
