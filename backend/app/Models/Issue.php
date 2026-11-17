<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Issue extends BaseModel
{
    use HasPublicId;

    protected function casts(): array
    {
        return ['issue_on' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(IssueLine::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(GoodsRequest::class, 'request_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'issued_by_officer_id');
    }

    public function acknowledgement(): HasOne
    {
        return $this->hasOne(IssueAcknowledgement::class);
    }
}
