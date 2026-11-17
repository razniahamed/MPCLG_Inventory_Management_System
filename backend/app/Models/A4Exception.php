<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class A4Exception extends BaseModel
{
    protected $table = 'a4_exceptions';

    public const TYPES = ['no_machine', 'reset', 'replacement', 'breakdown', 'manual', 'external_printing'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class, 'request_id');
    }
}
