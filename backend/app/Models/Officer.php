<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Models\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** The PERSON. A user account is separate and optional. */
class Officer extends Model
{
    use HasFactory, IsMasterData;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function publicService(): BelongsTo
    {
        return $this->belongsTo(PublicService::class);
    }

    public function serviceGrade(): BelongsTo
    {
        return $this->belongsTo(ServiceGrade::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OfficerAssignment::class)->orderByDesc('from_on');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
