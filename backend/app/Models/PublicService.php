<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicService extends Model
{
    use IsMasterData;

    protected $guarded = ['id'];

    public function grades(): HasMany
    {
        return $this->hasMany(ServiceGrade::class);
    }
}
