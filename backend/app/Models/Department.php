<?php

namespace App\Models;

use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A working unit (not a physical location). */
class Department extends Model
{
    use HasFactory, IsMasterData;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'active_from' => 'date',
            'active_to' => 'date',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id');
    }

    public function headOfficer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'head_officer_id');
    }

    public function coordinatorOfficer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'coordinator_officer_id');
    }

    public function defaultSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'default_site_id');
    }

    public function officers(): HasMany
    {
        return $this->hasMany(Officer::class);
    }
}
