<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Department request (goods or A4 paper). Named GoodsRequest to avoid
 * clashing with the HTTP Request class; the table is `requests`.
 */
class GoodsRequest extends BaseModel
{
    use HasPublicId;

    protected $table = 'requests';

    public const OPEN_FOR_ISSUE = ['approved', 'partly_issued'];

    protected function casts(): array
    {
        return [
            'period_from_on' => 'date',
            'period_to_on' => 'date',
            'required_on' => 'date',
            'submitted_at' => 'datetime',
            'current_version_no' => 'integer',
        ];
    }

    /** Every version's lines; use currentLines() / currentLinesLoaded() for the live version. */
    public function allLines(): HasMany
    {
        return $this->hasMany(RequestLine::class, 'request_id')->orderBy('version_no')->orderBy('line_no');
    }

    /** Query for the current version's lines (instance use). */
    public function currentLines(): HasMany
    {
        return $this->hasMany(RequestLine::class, 'request_id')
            ->where('version_no', $this->current_version_no)
            ->orderBy('line_no');
    }

    /** Current-version lines from an eager-loaded allLines relation. */
    public function currentLinesLoaded()
    {
        return $this->allLines->where('version_no', $this->current_version_no)->values();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'submitted_by_officer_id');
    }

    public function certifiedBy(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'certified_by_officer_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'request_id');
    }

    public function paperStatement(): BelongsTo
    {
        return $this->belongsTo(PaperStatement::class);
    }

    public function meterObservations(): BelongsToMany
    {
        return $this->belongsToMany(MeterObservation::class, 'request_meter_links', 'request_id', 'observation_id');
    }

    public function a4Exceptions(): HasMany
    {
        return $this->hasMany(A4Exception::class, 'request_id');
    }
}
