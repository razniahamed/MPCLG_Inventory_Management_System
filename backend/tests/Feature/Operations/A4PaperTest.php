<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnitConversion;
use App\Models\Machine;
use App\Models\MeterObservationReading;
use App\Models\Store;
use App\Services\Paper\MeterService;
use App\Services\Stock\UnitConverter;
use App\Services\System\SettingsService;
use Tests\Support\BuildsOperations;

uses(BuildsOperations::class);

beforeEach(function () {
    $this->department = Department::factory()->create();
    $this->other = Department::factory()->create();
    $this->store = Store::factory()->create();
    $this->clerk = $this->officerUser([['chief_clerk', 'department', $this->department->id]], $this->department);
    $this->verifier = $this->officerUser(['accountant_supply']);
    $this->tech = $this->officerUser(['repair_coordinator']);

    $this->actingAsUser($this->tech);
    $this->machine = $this->postJson('/api/v1/machines', [
        'code' => 'PRN-01',
        'leased_reference' => 'LEASE-2026-07',
        'model' => 'Canon iR 2630',
        'responsible_department_id' => $this->department->id,
        'is_shared' => true,
        'counter_types' => ['printed_sides', 'physical_sheets'],
        'shared_department_ids' => [$this->department->id, $this->other->id],
    ])->assertCreated()->json('data');
    $this->sides = collect($this->machine['counters'])->firstWhere('counter_type', 'printed_sides')['id'];
    $this->sheets = collect($this->machine['counters'])->firstWhere('counter_type', 'physical_sheets')['id'];
});

function reading($test, ?int $sides, ?int $sheets, array $extra = []): array
{
    $test->actingAsUser($test->clerk);

    return $test->postJson('/api/v1/meter-observations', $extra + [
        'machine_id' => $test->machine['id'],
        'readings' => [
            ['counter_id' => $test->sides, 'reading_value' => $sides, 'is_unknown' => $sides === null],
            ['counter_id' => $test->sheets, 'reading_value' => $sheets, 'is_unknown' => $sheets === null],
        ],
    ])->assertCreated()->json('data');
}

it('makes the first reading a baseline, flags decreases and never stores unknown as zero (rule 16)', function () {
    config(['mpclg.transaction_baseline' => 1]);
    app(SettingsService::class)->update(['a4.photo_required' => false], 'test');

    $first = reading($this, 10000, 6000, ['observed_at' => now()->subDays(10)->toDateTimeString()]);
    expect($first['is_baseline'])->toBeTrue()->and($first['status'])->toBe('submitted');

    $lower = reading($this, 9000, null, ['observed_at' => now()->subDays(5)->toDateTimeString()]);
    expect($lower['status'])->toBe('flagged')->and($lower['flag_reasons'])->toContain('decreased');

    $unknown = MeterObservationReading::where(['observation_id' => $lower['id'], 'counter_id' => $this->sheets])->first();
    expect($unknown->is_unknown)->toBeTrue()->and($unknown->reading_value)->toBeNull();
});

it('flags a missing photo, an overlapping period and an unusual jump', function () {
    reading($this, 1000, 600, ['observed_at' => now()->subDays(3)->toDateTimeString()]);
    $bad = reading($this, 90000, 600, ['observed_at' => now()->subDays(5)->toDateTimeString()]);

    expect($bad['flag_reasons'])->toContain('missing_photo')->toContain('period_overlap')->toContain('unusual_increase');
});

it('needs an approved baseline for a reset and never counts usage across it (acceptance: shared or reset machine)', function () {
    app(SettingsService::class)->update(['a4.photo_required' => false], 'test');
    $from = now()->subDays(20)->toDateString();
    reading($this, 1000, 500, ['observed_at' => now()->subDays(15)->toDateTimeString()]);
    reading($this, 3000, 1500, ['observed_at' => now()->subDays(10)->toDateTimeString()]);

    $this->assertApiError($this->postJson('/api/v1/meter-observations', [
        'machine_id' => $this->machine['id'], 'is_baseline' => true,
        'readings' => [['counter_id' => $this->sides, 'reading_value' => 5]],
    ]), 422, 'VALIDATION_ERROR');

    $reset = reading($this, 5, 3, ['is_baseline' => true, 'baseline_reason' => 'reset', 'observed_at' => now()->subDays(8)->toDateTimeString()]);
    expect($reset['flag_reasons'])->toContain('baseline_needs_approval');
    $this->actingAsUser($this->verifier);
    $this->postJson("/api/v1/meter-observations/{$reset['id']}/verify", ['decision' => 'accept'])->assertOk();

    reading($this, 505, 253, ['observed_at' => now()->subDays(2)->toDateTimeString()]);

    // 1000→3000 = 2000, (reset) 5→505 = 500 ⇒ 2500, not 3000 − 505 nor negative.
    $usage = app(MeterService::class)->usageBetween(Machine::find($this->machine['id']), 'printed_sides', $from, now()->toDateString());
    expect($usage)->toBe(2500);

    // Shared usage can never be allocated twice over.
    $this->actingAsUser($this->clerk);
    $this->assertApiError($this->postJson('/api/v1/shared-usage-allocations', [
        'machine_id' => $this->machine['id'], 'period_from_on' => $from, 'period_to_on' => now()->toDateString(),
        'lines' => [['department_id' => $this->department->id, 'allocated_usage' => 2500], ['department_id' => $this->other->id, 'allocated_usage' => 2500]],
    ]), 422, 'BUSINESS_RULE_VIOLATION');

    $this->postJson('/api/v1/shared-usage-allocations', [
        'machine_id' => $this->machine['id'], 'period_from_on' => $from, 'period_to_on' => now()->toDateString(),
        'lines' => [['department_id' => $this->department->id, 'allocated_usage' => 1500, 'basis' => 'measured'], ['department_id' => $this->other->id, 'allocated_usage' => 1000]],
    ])->assertCreated()->assertJsonPath('data.total_usage', 2500);
});

it('converts reams to sheets only with an approved conversion; printed sides are not sheets (acceptance: paper units and duplex)', function () {
    $paper = Item::create([
        'item_code' => 'A4-80', 'name_en' => 'A4 Paper 80gsm', 'category_id' => ItemCategory::where('code', 'PAPER')->value('id'),
        'tracking_method' => 'quantity', 'base_unit_id' => $this->unit('SHT')->id, 'purchase_unit_id' => $this->unit('REAM')->id,
        'issue_unit_id' => $this->unit('REAM')->id, 'is_paper' => true,
    ]);

    $this->actingAsUser($this->clerk);
    $this->assertApiError($this->postJson('/api/v1/requests', [
        'request_type' => 'a4_paper', 'department_id' => $this->department->id, 'store_id' => $this->store->id,
        'lines' => [['item_id' => $paper->id, 'unit_id' => $this->unit('REAM')->id, 'requested_qty' => 2]],
    ]), 422, 'BUSINESS_RULE_VIOLATION');

    ItemUnitConversion::create(['item_id' => $paper->id, 'from_unit_id' => $this->unit('REAM')->id, 'to_unit_id' => $this->unit('SHT')->id, 'factor' => 500, 'approved_at' => now()]);
    expect(app(UnitConverter::class)->toBase($paper, $this->unit('REAM')->id, 2))->toBe('1000.000');

    // Duplex: 1000 printed sides used 500 physical sheets — separate counters, never mixed.
    app(SettingsService::class)->update(['a4.photo_required' => false], 'test');
    reading($this, 0, 0, ['observed_at' => now()->subDays(5)->toDateTimeString()]);
    reading($this, 1000, 500, ['observed_at' => now()->subDay()->toDateTimeString()]);
    $machine = Machine::find($this->machine['id']);
    $service = app(MeterService::class);
    expect($service->usageBetween($machine, 'printed_sides', now()->subDays(6)->toDateString(), now()->toDateString()))->toBe(1000)
        ->and($service->usageBetween($machine, 'physical_sheets', now()->subDays(6)->toDateString(), now()->toDateString()))->toBe(500);
});

it('blocks an A4 request without evidence and accepts it with a statement and readings', function () {
    $paper = Item::create([
        'item_code' => 'A4-75', 'name_en' => 'A4 Paper 75gsm', 'category_id' => ItemCategory::where('code', 'PAPER')->value('id'),
        'tracking_method' => 'quantity', 'base_unit_id' => $this->unit('REAM')->id, 'purchase_unit_id' => $this->unit('REAM')->id,
        'issue_unit_id' => $this->unit('REAM')->id, 'is_paper' => true,
    ]);

    $this->actingAsUser($this->clerk);
    $request = $this->postJson('/api/v1/requests', [
        'request_type' => 'a4_paper', 'department_id' => $this->department->id, 'store_id' => $this->store->id,
        'lines' => [['item_id' => $paper->id, 'requested_qty' => 5]],
    ])->assertCreated()->json('data');

    $this->assertApiError($this->postJson("/api/v1/requests/{$request['public_id']}/submit"), 422, 'BUSINESS_RULE_VIOLATION')
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'missing evidence'));

    $statement = $this->postJson('/api/v1/paper-statements', [
        'department_id' => $this->department->id, 'item_id' => $paper->id,
        'period_from_on' => now()->subMonth()->toDateString(), 'period_to_on' => now()->toDateString(),
        'opening_balance' => 4, 'receipts' => 6, 'usage_or_loss' => 9, 'counted_closing' => 1,
    ])->assertCreated()->assertJsonPath('data.unexplained_difference', '0.000')->json('data');

    app(SettingsService::class)->update(['a4.photo_required' => false], 'test');
    $obs = reading($this, 100, 60);

    $this->actingAsUser($this->clerk);
    $this->patchJson("/api/v1/requests/{$request['public_id']}", ['paper_statement_id' => $statement['id'], 'observation_ids' => [$obs['id']]])->assertOk();
    $this->postJson("/api/v1/requests/{$request['public_id']}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
});
