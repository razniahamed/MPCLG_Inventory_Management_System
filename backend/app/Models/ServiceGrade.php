<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceGrade extends Model
{
    use IsMasterData;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rank' => 'integer'];
    }

    public function publicService(): BelongsTo
    {
        return $this->belongsTo(PublicService::class);
    }
}
