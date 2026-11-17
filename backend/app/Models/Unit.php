<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use IsMasterData;

    protected $guarded = ['id'];
}
