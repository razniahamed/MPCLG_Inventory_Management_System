<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A controlled asset with a permanent number and QR token. */
class Asset extends BaseModel
{
    use HasPublicId;

    public const STATUSES = ['in_store', 'in_use', 'in_transit', 'under_repair', 'on_loan', 'lost', 'disposed'];

    public const CONDITIONS = ['new', 'good', 'fair', 'poor', 'damaged'];

    public const SOURCES = ['new_purchase', 'existing_in_house', 'import', 'donation', 'transfer_in'];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'in_service_on' => 'date',
            'last_verified_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function responsibleDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'responsible_department_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function expectedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'expected_location_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'custodian_officer_id');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'replaces_asset_id');
    }

    public function custodyHistory(): HasMany
    {
        return $this->hasMany(AssetCustodyHistory::class)->orderByDesc('from_at')->orderByDesc('id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class)->orderByDesc('id');
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class)->orderByDesc('ends_on');
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(RepairCase::class)->orderByDesc('id');
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(Valuation::class)->orderByDesc('effective_on')->orderByDesc('id');
    }

    public function serviceSchedules(): HasMany
    {
        return $this->hasMany(ServiceSchedule::class);
    }
}
