<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentReturn extends BaseModel
{
    use HasPublicId;

    protected function casts(): array
    {
        return ['return_on' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DepartmentReturnLine::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function originalIssue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'original_issue_id');
    }
}
