<?php

namespace App\Models;

use App\Enums\DesignationCategory;
use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use HasFactory, IsMasterData;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'category' => DesignationCategory::class,
            'is_head_post' => 'boolean',
        ];
    }
}
