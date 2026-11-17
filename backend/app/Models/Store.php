<?php

namespace App\Models;

use App\Enums\StoreType;
use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory, IsMasterData;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['store_type' => StoreType::class];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function responsibleOfficer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'responsible_officer_id');
    }

    public function storeOfficers(): HasMany
    {
        return $this->hasMany(StoreOfficer::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
