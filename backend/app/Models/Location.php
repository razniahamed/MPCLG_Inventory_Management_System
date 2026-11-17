<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Room / shelf / rack hierarchy inside a site. */
class Location extends Model
{
    use HasFactory, IsMasterData;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['location_type' => LocationType::class];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_location_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_location_id');
    }
}
