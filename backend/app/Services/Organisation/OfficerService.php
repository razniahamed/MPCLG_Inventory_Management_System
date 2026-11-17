<?php

namespace App\Services\Organisation;

use App\Enums\AuditAction;
use App\Enums\EmploymentStatus;
use App\Exceptions\ApiException;
use App\Models\Officer;
use App\Models\OfficerAssignment;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Officers (the people) and their assignment history, so old transactions keep
 * the correct designation and department.
 */
class OfficerService
{
    private const ASSIGNMENT_FIELDS = ['designation_id', 'department_id', 'public_service_id', 'service_grade_id'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MasterDataService $masterData,
    ) {}

    public function create(array $data): Officer
    {
        return DB::transaction(function () use ($data) {
            // authority_ref belongs to the opening assignment, not the officer row.
            $officer = $this->masterData->create(Officer::class, Arr::except($data, ['authority_ref']));

            OfficerAssignment::create([
                'officer_id' => $officer->id,
                'designation_id' => $officer->designation_id,
                'department_id' => $officer->department_id,
                'public_service_id' => $officer->public_service_id,
                'service_grade_id' => $officer->service_grade_id,
                'from_on' => $officer->joined_on ?? now()->toDateString(),
                'authority_ref' => $data['authority_ref'] ?? null,
            ]);

            return $officer;
        });
    }

    /**
     * Update the officer. Designation / department / service changes must go
     * through a new assignment so history is kept. Leaving (transferred,
     * retired, resigned) closes the open assignment.
     *
     * @return array{officer: Officer, warnings: array<int, string>}
     */
    public function update(Officer $officer, array $data): array
    {
        $changingPost = array_filter(
            array_intersect_key($data, array_flip(self::ASSIGNMENT_FIELDS)),
            fn ($value, $key) => (string) $value !== (string) $officer->{$key},
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changingPost !== []) {
            throw ApiException::businessRule('Change designation, department or service through POST /officers/{id}/assignments so the history is kept.');
        }

        $newStatus = isset($data['employment_status']) ? EmploymentStatus::from($data['employment_status']) : null;
        $leaving = $newStatus !== null && $newStatus !== $officer->employment_status && $newStatus->hasLeft();

        return DB::transaction(function () use ($officer, $data, $leaving) {
            $officer = $this->masterData->update($officer, Arr::except($data, ['authority_ref']));
            $warnings = [];

            if ($leaving) {
                $leftOn = $officer->left_on ?? now()->toDateString();
                if (! $officer->left_on) {
                    $officer->forceFill(['left_on' => $leftOn])->save();
                }

                OfficerAssignment::where('officer_id', $officer->id)->whereNull('to_on')->update(['to_on' => $leftOn]);

                if ($officer->user && $officer->user->status->canSignIn()) {
                    $warnings[] = "The officer still has an active user account ({$officer->user->username}). Deactivate it through POST /users/{$officer->user->public_id}/deactivate.";
                }
            }

            return ['officer' => $officer->fresh(), 'warnings' => $warnings];
        });
    }

    /**
     * New post (transfer / promotion): closes the open assignment the day
     * before and makes the new one current.
     */
    public function addAssignment(Officer $officer, array $data): OfficerAssignment
    {
        if ($officer->employment_status->hasLeft()) {
            throw ApiException::businessRule('The officer has left the ministry; reinstate them before adding an assignment.');
        }

        $fromOn = Carbon::parse($data['from_on'])->startOfDay();

        return DB::transaction(function () use ($officer, $data, $fromOn) {
            $open = OfficerAssignment::where('officer_id', $officer->id)->whereNull('to_on')->lockForUpdate()->first();

            if ($open && $open->from_on->gte($fromOn)) {
                throw ApiException::businessRule('The new assignment must start after the current one ('.$open->from_on->toDateString().').');
            }

            $open?->update(['to_on' => $fromOn->copy()->subDay()->toDateString()]);

            $assignment = OfficerAssignment::create([
                'officer_id' => $officer->id,
                'designation_id' => $data['designation_id'],
                'department_id' => $data['department_id'],
                'public_service_id' => $data['public_service_id'] ?? null,
                'service_grade_id' => $data['service_grade_id'] ?? null,
                'from_on' => $fromOn->toDateString(),
                'authority_ref' => $data['authority_ref'] ?? null,
            ]);

            $original = $officer->getAttributes();
            $officer->fill(array_intersect_key($data, array_flip(self::ASSIGNMENT_FIELDS)))->save();
            $this->audit->logChanges($officer, $original, $data['reason'] ?? ('New assignment from '.$fromOn->toDateString()));
            $this->audit->log(AuditAction::Created, $assignment, null, $assignment->attributesToArray(), $data['reason'] ?? null);

            return $assignment;
        });
    }
}
