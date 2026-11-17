<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalFlow extends Model
{
    use IsMasterData;

    protected $guarded = ['id'];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalFlowStep::class, 'flow_id')->orderBy('step_no');
    }
}
