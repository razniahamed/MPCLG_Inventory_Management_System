<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** THE source of truth for stock. Append-only. */
class StockLedger extends Model
{
    use AppendOnly, HasAuditColumns;

    protected $table = 'stock_ledger';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['qty_signed' => 'decimal:3', 'event_on' => 'date', 'expiry_on' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class);
    }
}
