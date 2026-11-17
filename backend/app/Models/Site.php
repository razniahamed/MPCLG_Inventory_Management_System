<?php

namespace App\Models;

use App\Enums\SiteType;
use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory, IsMasterData;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['site_type' => SiteType::class];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
