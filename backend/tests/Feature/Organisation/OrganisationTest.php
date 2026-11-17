<?php

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Division;
use App\Models\Location;
use App\Models\Officer;
use App\Models\OfficerAssignment;
use App\Models\Site;
use App\Models\Store;
use App\Models\User;

beforeEach(function () {
    $this->actingAsUser($this->admin());
});

it('has the seeded divisions, draft departments and designations', function () {
    $this->getJson('/api/v1/divisions?per_page=100')->assertOk()->assertJsonPath('meta.total', 7);
    $this->getJson('/api/v1/departments?per_page=100')->assertOk()->assertJsonPath('meta.total', 15);
    $this->getJson('/api/v1/designations?search=Accountant')->assertOk()->assertJsonPath('meta.total', 3);
    $this->getJson('/api/v1/public-services')->assertOk()->assertJsonPath('meta.total', 6);
});

it('creates, updates and retires a division with audit trail', function () {
    $id = $this->postJson('/api/v1/divisions', ['code' => 'NEW', 'name_en' => 'New Division', 'reason' => 'Restructure'])
        ->assertCreated()->assertJsonPath('data.code', 'NEW')->json('data.id');

    $this->patchJson("/api/v1/divisions/{$id}", ['name_en' => 'Renamed Division'])
        ->assertOk()->assertJsonPath('data.name_en', 'Renamed Division');

    $this->assertApiError($this->postJson('/api/v1/divisions', ['code' => 'NEW', 'name_en' => 'Dup']), 422, 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['code']]);

    $this->assertApiError($this->deleteJson("/api/v1/divisions/{$id}"), 422, 'VALIDATION_ERROR');
    $this->deleteJson("/api/v1/divisions/{$id}", ['reason' => 'Merged'])->assertOk();

    expect(Division::withTrashed()->find($id))->is_active->toBeFalse()->trashed()->toBeTrue();
    $this->assertApiError($this->getJson("/api/v1/divisions/{$id}"), 404, 'NOT_FOUND');
    expect(AuditLog::where('auditable_type', 'divisions')->where('auditable_id', $id)->pluck('action')->map->value->all())
        ->toBe(['created', 'updated', 'deleted']);
});

it('refuses to retire master records that are still in use', function () {
    $division = Division::factory()->create();
    Department::factory()->create(['division_id' => $division->id]);

    $this->assertApiError($this->deleteJson("/api/v1/divisions/{$division->id}", ['reason' => 'test']), 422, 'BUSINESS_RULE_VIOLATION');
});

it('manages departments within divisions and prevents cycles', function () {
    $division = Division::factory()->create();

    $parent = $this->postJson('/api/v1/departments', [
        'code' => 'P1', 'name_en' => 'Parent', 'division_id' => $division->id, 'active_from' => '2026-01-01',
    ])->assertCreated()->json('data.id');

    $child = $this->postJson('/api/v1/departments', [
        'code' => 'C1', 'name_en' => 'Child', 'division_id' => $division->id,
        'parent_department_id' => $parent, 'active_from' => '2026-01-01',
    ])->assertCreated()->assertJsonPath('data.parent.id', $parent)->json('data.id');

    $this->assertApiError($this->patchJson("/api/v1/departments/{$parent}", ['parent_department_id' => $child]), 422, 'BUSINESS_RULE_VIOLATION');

    $this->getJson("/api/v1/departments?filter[division_id]={$division->id}")->assertOk()->assertJsonPath('meta.total', 2);
});

it('manages sites, stores, store officers and locations', function () {
    $site = $this->postJson('/api/v1/sites', ['code' => 'BNG', 'name' => 'Circuit Bungalow', 'site_type' => 'bungalow'])
        ->assertCreated()->json('data.id');

    $this->assertApiError($this->postJson('/api/v1/sites', ['code' => 'X', 'name' => 'X', 'site_type' => 'castle']), 422, 'VALIDATION_ERROR');

    $store = $this->postJson('/api/v1/stores', ['code' => 'ST-STAT', 'name' => 'Stationery Store', 'site_id' => $site, 'store_type' => 'stationery'])
        ->assertCreated()->assertJsonPath('data.site.id', $site)->json('data.id');

    $officer = Officer::factory()->create();
    $this->putJson("/api/v1/stores/{$store}/officers", ['officers' => [['officer_id' => $officer->id]]])
        ->assertOk()->assertJsonPath('data.0.officer_id', $officer->id)->assertJsonPath('data.0.role', 'storekeeper');

    $room = $this->postJson('/api/v1/locations', ['site_id' => $site, 'store_id' => $store, 'location_type' => 'room', 'code' => 'R1', 'name' => 'Room 1'])
        ->assertCreated()->json('data.id');
    $this->postJson('/api/v1/locations', ['site_id' => $site, 'parent_location_id' => $room, 'location_type' => 'shelf', 'code' => 'R1-S1', 'name' => 'Shelf 1'])
        ->assertCreated();

    // Same code allowed on another site, not on the same one.
    $this->assertApiError($this->postJson('/api/v1/locations', ['site_id' => $site, 'location_type' => 'room', 'code' => 'R1', 'name' => 'Dup']), 422, 'VALIDATION_ERROR');
    $other = Site::factory()->create();
    $this->postJson('/api/v1/locations', ['site_id' => $other->id, 'location_type' => 'room', 'code' => 'R1', 'name' => 'Room 1'])->assertCreated();

    // A store from another site is rejected.
    $foreignStore = Store::factory()->create(['site_id' => $other->id]);
    $this->assertApiError($this->postJson('/api/v1/locations', ['site_id' => $site, 'store_id' => $foreignStore->id, 'location_type' => 'room', 'code' => 'R9', 'name' => 'x']), 422, 'BUSINESS_RULE_VIOLATION');

    $this->assertApiError($this->deleteJson("/api/v1/locations/{$room}", ['reason' => 'test']), 422, 'BUSINESS_RULE_VIOLATION');
    expect(Location::where('site_id', $site)->count())->toBe(2);
});

it('creates an officer with an opening assignment and keeps history on transfer', function () {
    $designation = Designation::where('code', 'MSO')->first();
    $from = Department::where('code', 'IT')->first();
    $to = Department::where('code', 'ACC_SUP')->first();

    $officerId = $this->postJson('/api/v1/officers', [
        'officer_no' => 'EMP-1001',
        'full_name' => 'Kamal Perera',
        'designation_id' => $designation->id,
        'department_id' => $from->id,
        'joined_on' => '2024-03-01',
    ])->assertCreated()->assertJsonPath('data.department.code', 'IT')->json('data.id');

    expect(OfficerAssignment::where('officer_id', $officerId)->count())->toBe(1);

    // Direct department change is refused: history must be kept.
    $this->assertApiError($this->patchJson("/api/v1/officers/{$officerId}", ['department_id' => $to->id]), 422, 'BUSINESS_RULE_VIOLATION');

    $this->postJson("/api/v1/officers/{$officerId}/assignments", [
        'designation_id' => $designation->id,
        'department_id' => $to->id,
        'from_on' => '2026-06-01',
        'authority_ref' => 'TR/2026/55',
    ])->assertCreated()->assertJsonPath('data.is_current', true);

    $history = $this->getJson("/api/v1/officers/{$officerId}/assignments")->assertOk()->json('data');
    expect($history)->toHaveCount(2)
        ->and($history[0]['department']['code'])->toBe('ACC_SUP')
        ->and($history[1]['to_on'])->toBe('2026-05-31');

    expect(Officer::find($officerId)->department_id)->toBe($to->id);

    $this->assertApiError($this->postJson("/api/v1/officers/{$officerId}/assignments", [
        'designation_id' => $designation->id, 'department_id' => $from->id, 'from_on' => '2026-01-01',
    ]), 422, 'BUSINESS_RULE_VIOLATION');
});

it('closes the assignment and warns about an active account when an officer leaves', function () {
    $officer = Officer::factory()->create();
    OfficerAssignment::create([
        'officer_id' => $officer->id, 'designation_id' => $officer->designation_id,
        'department_id' => $officer->department_id, 'from_on' => '2024-01-01',
    ]);
    $user = User::factory()->create(['officer_id' => $officer->id]);

    $response = $this->patchJson("/api/v1/officers/{$officer->id}", ['employment_status' => 'retired', 'left_on' => '2026-10-31'])
        ->assertOk()->assertJsonPath('data.employment_status', 'retired');

    expect((string) $response->json('meta.warnings.0'))->toContain($user->public_id);
    expect(OfficerAssignment::where('officer_id', $officer->id)->first()->to_on->toDateString())->toBe('2026-10-31');

    // Cannot retire the officer record while the account is still live.
    $this->assertApiError($this->deleteJson("/api/v1/officers/{$officer->id}", ['reason' => 'test']), 422, 'BUSINESS_RULE_VIOLATION');
});

it('lets department-scoped readers see departments but not manage them', function () {
    $this->actingAsUser($this->userWithRoles(['department_head']));

    $this->getJson('/api/v1/departments')->assertOk();
    $this->assertApiError($this->postJson('/api/v1/departments', []), 403, 'PERMISSION_DENIED');
    $this->assertApiError($this->postJson('/api/v1/divisions', []), 403, 'PERMISSION_DENIED');
});
