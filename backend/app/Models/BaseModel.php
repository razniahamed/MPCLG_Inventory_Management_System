<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;

/** Business table with [std] audit columns. */
abstract class BaseModel extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];
}
