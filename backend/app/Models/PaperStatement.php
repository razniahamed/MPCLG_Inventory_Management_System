<?php

namespace App\Models;

use App\Support\Dec;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Department paper balance for a period. */
class PaperStatement extends BaseModel
{
    protected function casts(): array
    {
        return [
            'period_from_on' => 'date',
            'period_to_on' => 'date',
            'opening_balance' => 'decimal:3',
            'receipts' => 'decimal:3',
            'transfers' => 'decimal:3',
            'returns' => 'decimal:3',
            'usage_or_loss' => 'decimal:3',
            'counted_closing' => 'decimal:3',
            'certified_at' => 'datetime',
            'is_baseline' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** opening + receipts + transfers − returns − usage. */
    public function expectedClosing(): string
    {
        return Dec::sub(Dec::sub(Dec::add(Dec::add($this->opening_balance, $this->receipts), $this->transfers), $this->returns), $this->usage_or_loss);
    }

    public function unexplainedDifference(): string
    {
        return Dec::sub($this->counted_closing, $this->expectedClosing());
    }
}
