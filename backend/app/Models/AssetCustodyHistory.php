<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetCustodyHistory extends Model
{
    use AppendOnly, HasAuditColumns;

    protected $table = 'asset_custody_history';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
