<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Printer / photocopier: an owned asset or a leased machine. */
class Machine extends Model
{
    use IsMasterData;

    public const COUNTER_TYPES = ['printed_sides', 'physical_sheets', 'a4_mono', 'a4_colour', 'a3_mono', 'a3_colour'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_shared' => 'boolean'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function responsibleDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'responsible_department_id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(MachineCounter::class);
    }

    public function departmentAssignments(): HasMany
    {
        return $this->hasMany(MachineDepartmentAssignment::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(MeterObservation::class);
    }
}
