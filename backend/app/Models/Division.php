<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One of the branches under the Secretary. */
class Division extends Model
{
    use HasFactory, IsMasterData;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function headDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'head_designation_id');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
