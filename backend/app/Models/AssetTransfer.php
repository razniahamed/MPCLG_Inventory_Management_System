<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransfer extends BaseModel
{
    use HasPublicId;

    public const OPEN = ['submitted', 'approved', 'dispatched'];

    protected $hidden = ['open_key'];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'returned_at' => 'datetime',
            'expected_return_on' => 'date',
            'serial_verified' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function fromCustodian(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'from_custodian_officer_id');
    }

    public function toCustodian(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'to_custodian_officer_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'requested_by_officer_id');
    }
}
