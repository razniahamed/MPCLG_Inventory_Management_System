<?php

namespace App\Services\Paper;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Attachment;
use App\Models\Machine;
use App\Models\MachineCounter;
use App\Models\MeterObservation;
use App\Models\MeterObservationReading;
use App\Services\Access\Actor;
use App\Services\Access\Scope;
use App\Services\Audit\AuditLogger;
use App\Services\System\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Meter readings (rule 16): a reading below the previous one, an overlapping
 * period, a missing photo or an unusual increase is FLAGGED. Unknown is never
 * zero. A reset or replacement needs an approved new baseline.
 */
class MeterService
{
    /** Increase over the previous reading that is treated as unusual. */
    public const UNUSUAL_INCREASE = 20000;

    public function __construct(
        private readonly Actor $actor,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function createMachine(array $data): Machine
    {
        $this->actor->authorize('machine.manage');

        return DB::transaction(function () use ($data) {
            $machine = Machine::create([
                'code' => $data['code'],
                'asset_id' => $data['asset_id'] ?? null,
                'leased_reference' => $data['leased_reference'] ?? null,
                'model' => $data['model'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'responsible_department_id' => $data['responsible_department_id'],
                'is_shared' => (bool) ($data['is_shared'] ?? false),
            ]);

            foreach ($data['counter_types'] ?? ['printed_sides'] as $type) {
                MachineCounter::create(['machine_id' => $machine->id, 'counter_type' => $type]);
            }

            foreach ($data['shared_department_ids'] ?? [] as $departmentId) {
                $machine->departmentAssignments()->create(['department_id' => $departmentId, 'from_on' => now()->toDateString()]);
            }

            $this->audit->log(AuditAction::Created, $machine, null, $machine->only(['code', 'model', 'is_shared']));

            return $machine->load(['counters', 'departmentAssignments']);
        });
    }

    /**
     * @param  array<int, array{counter_id: int, reading_value?: ?int, is_unknown?: bool}>  $readings
     */
    public function record(array $data, ?UploadedFile $photo = null): MeterObservation
    {
        $machine = Machine::with('counters')->findOrFail($data['machine_id']);
        $this->actor->authorize('meter.record', Scope::department($machine->responsible_department_id));
        $officer = $this->actor->officer();

        $observedAt = isset($data['observed_at']) ? Carbon::parse($data['observed_at']) : now();
        $isBaseline = (bool) ($data['is_baseline'] ?? false);
        // Compare with the latest reading on record, whatever its status: a
        // reading flagged only for a missing photo still sets the chain.
        $previous = MeterObservation::where('machine_id', $machine->id)
            ->orderByDesc('observed_at')->orderByDesc('id')
            ->first();

        if ($previous === null && ! $isBaseline) {
            $isBaseline = true; // the first reading of a machine is its baseline
            $data['baseline_reason'] ??= 'first_reading';
        }
        if ($isBaseline && $previous !== null && empty($data['baseline_reason'])) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, ['baseline_reason' => ['Say why a new baseline is needed (reset or replacement).']]);
        }

        $counterIds = $machine->counters->pluck('id')->all();
        $flags = [];
        $values = [];

        foreach ($data['readings'] as $reading) {
            if (! in_array((int) $reading['counter_id'], $counterIds, true)) {
                throw ApiException::businessRule('A counter does not belong to this machine.');
            }
            $unknown = (bool) ($reading['is_unknown'] ?? false);
            if (! $unknown && ! isset($reading['reading_value'])) {
                throw ApiException::businessRule('Enter a reading or mark it as unknown (unknown is never recorded as zero).');
            }
            $values[(int) $reading['counter_id']] = $unknown ? null : (int) $reading['reading_value'];
        }

        if ($previous && ! $isBaseline) {
            if ($observedAt->lte($previous->observed_at)) {
                $flags[] = 'period_overlap';
            }
            foreach ($previous->readings as $old) {
                $new = $values[$old->counter_id] ?? null;
                if ($new === null || $old->reading_value === null) {
                    continue;
                }
                if ($new < $old->reading_value) {
                    $flags[] = 'decreased';
                } elseif ($new - $old->reading_value > self::UNUSUAL_INCREASE) {
                    $flags[] = 'unusual_increase';
                }
            }
        }

        if ($photo === null && $this->settings->get('a4.photo_required')) {
            $flags[] = 'missing_photo';
        }

        if ($isBaseline && $previous !== null) {
            $flags[] = 'baseline_needs_approval';
        }

        $flags = array_values(array_unique($flags));

        return DB::transaction(function () use ($machine, $officer, $observedAt, $isBaseline, $data, $previous, $values, $flags, $photo) {
            $observation = MeterObservation::create([
                'machine_id' => $machine->id,
                'observed_at' => $observedAt,
                'observed_by_officer_id' => $officer->id,
                'is_baseline' => $isBaseline,
                'baseline_reason' => $data['baseline_reason'] ?? null,
                'previous_observation_id' => $previous?->id,
                'status' => $flags === [] ? 'submitted' : 'flagged',
                'flag_reasons' => $flags ?: null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($values as $counterId => $value) {
                MeterObservationReading::create([
                    'observation_id' => $observation->id,
                    'counter_id' => $counterId,
                    'reading_value' => $value,
                    'is_unknown' => $value === null,
                ]);
            }

            if ($photo) {
                $observation->update(['photo_attachment_id' => $this->storePhoto($photo, $observation)->id]);
            }

            $this->audit->log(AuditAction::Created, $observation, null, ['machine' => $machine->code, 'flags' => $flags]);

            return $observation->load('readings.counter');
        });
    }

    /** Verifier accepts (including an approved new baseline) or flags a reading. */
    public function verify(MeterObservation $observation, string $decision, ?string $remarks): MeterObservation
    {
        $this->actor->authorize('meter.verify');
        $this->actor->assertNotSelf($observation->observed_by_officer_id, 'a reading you recorded');

        if (! in_array($observation->status, ['submitted', 'flagged'], true)) {
            throw ApiException::businessRule('This reading has already been verified.');
        }
        if ($decision === 'flag' && blank($remarks)) {
            throw new ApiException('Validation failed.', 'VALIDATION_ERROR', 422, ['remarks' => ['Explain why the reading is flagged.']]);
        }

        return DB::transaction(function () use ($observation, $decision, $remarks) {
            $observation->update([
                'status' => $decision === 'accept' ? 'accepted' : 'flagged',
                'verified_by_officer_id' => $this->actor->officerId(),
                'verified_at' => now(),
                'remarks' => $remarks ?? $observation->remarks,
            ]);
            $this->audit->log(AuditAction::StatusChanged, $observation, null, ['status' => $observation->status], $remarks);

            return $observation;
        });
    }

    /**
     * Usage between two accepted observations for one counter (null when either
     * reading is unknown, or a baseline sits in between).
     */
    public function usageBetween(Machine $machine, string $counterType, string $from, string $to): ?int
    {
        $counter = $machine->counters()->where('counter_type', $counterType)->first();
        if (! $counter) {
            return null;
        }

        $readings = MeterObservationReading::query()
            ->where('counter_id', $counter->id)
            ->whereHas('observation', fn ($q) => $q->whereIn('status', ['accepted', 'submitted'])->whereBetween('observed_at', [$from, $to.' 23:59:59']))
            ->with('observation')
            ->get()
            ->sortBy(fn ($r) => $r->observation->observed_at)
            ->values();

        if ($readings->count() < 2) {
            return null;
        }

        $total = 0;
        for ($i = 1; $i < $readings->count(); $i++) {
            $prev = $readings[$i - 1];
            $cur = $readings[$i];
            if ($cur->observation->is_baseline) {
                continue; // reset / replacement: never subtract across a baseline
            }
            if ($prev->reading_value === null || $cur->reading_value === null) {
                return null;
            }
            $total += max(0, $cur->reading_value - $prev->reading_value);
        }

        return $total;
    }

    private function storePhoto(UploadedFile $file, MeterObservation $observation): Attachment
    {
        $path = $file->store('meter-photos', 'local');

        return Attachment::create([
            'attachable_type' => 'meter_observations',
            'attachable_id' => $observation->id,
            'document_type' => 'meter_photo',
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $this->actor->user()->id,
            'uploaded_at' => now(),
        ]);
    }
}
