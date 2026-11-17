<?php

use App\Models\Asset;
use App\Models\AssetCustodyHistory;
use App\Models\Department;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Store;
use App\Models\UserPermission;
use App\Models\Warranty;
use Tests\Support\BuildsOperations;

uses(BuildsOperations::class);

beforeEach(function () {
    $this->store = Store::factory()->create();
    $this->from = Department::factory()->create();
    $this->to = Department::factory()->create();
    $this->room = Location::factory()->create();
    $this->laptop = $this->makeItem(['tracking_method' => 'asset', 'name_en' => 'Laptop computer']);

    $this->storekeeper = $this->officerUser([['storekeeper', 'store', $this->store->id]]);
    $this->clerk = $this->officerUser([['chief_clerk', 'department', $this->from->id]], $this->from);
    $this->approver = $this->officerUser(['accountant_supply']);
    $this->receiver = $this->officerUser([['chief_clerk', 'department', $this->to->id]], $this->to);
    $this->tech = $this->officerUser(['repair_coordinator']);

    $this->actingAsUser($this->storekeeper);
    $this->asset = $this->postJson('/api/v1/assets', [
        'item_id' => $this->laptop->id,
        'serial_no' => 'SN-12345',
        'model' => 'ThinkPad T14',
        'responsible_department_id' => $this->from->id,
    ])->assertCreated()->json('data');
});

it('registers assets with a permanent number and an unguessable QR token', function () {
    expect($this->asset['asset_no'])->toStartWith('AST-')
        ->and($this->asset['qr_token'])->toMatch('/^[a-f0-9]{32}$/')
        ->and($this->asset['acquired_on_quality'])->toBe('unknown'); // never invent dates

    $scan = $this->getJson("/api/v1/assets/scan/{$this->asset['qr_token']}")->assertOk()->json('data');
    expect($scan['asset_no'])->toBe($this->asset['asset_no'])->and($scan)->not->toHaveKey('value_reference');

    $this->assertApiError($this->postJson('/api/v1/assets', ['item_id' => $this->makeItem()->id]), 422, 'BUSINESS_RULE_VIOLATION');
});

it('moves an asset only when the receiver confirms; the QR never changes (acceptance: transfer and QR)', function () {
    $this->actingAsUser($this->clerk);
    $transfer = $this->postJson('/api/v1/asset-transfers', [
        'asset_id' => $this->asset['public_id'],
        'to_department_id' => $this->to->id,
        'to_location_id' => $this->room->id,
        'to_custodian_officer_id' => $this->receiver->officer_id,
        'reason' => 'Officer transferred',
    ])->assertCreated()->json('data');

    // A second open transfer for the same asset is refused.
    $this->assertApiError($this->postJson('/api/v1/asset-transfers', ['asset_id' => $this->asset['public_id'], 'to_department_id' => $this->to->id]), 409, 'CONFLICT');

    $this->actingAsUser($this->approver);
    $this->postJson("/api/v1/asset-transfers/{$transfer['public_id']}/approve")->assertOk();
    expect(Asset::find($this->asset['id'])->responsible_department_id)->toBe($this->from->id); // approval ≠ moved

    $this->actingAsUser($this->storekeeper);
    $this->postJson("/api/v1/asset-transfers/{$transfer['public_id']}/dispatch", ['condition' => 'good'])->assertOk();
    expect(Asset::find($this->asset['id'])->operational_status)->toBe('in_transit');

    $this->actingAsUser($this->receiver);
    $this->assertApiError($this->postJson("/api/v1/asset-transfers/{$transfer['public_id']}/receive", ['condition' => 'good', 'serial_verified' => false]), 422, 'VALIDATION_ERROR');
    $this->postJson("/api/v1/asset-transfers/{$transfer['public_id']}/receive", ['condition' => 'good', 'serial_verified' => true])->assertOk();

    $asset = Asset::find($this->asset['id']);
    expect($asset->operational_status)->toBe('in_use')
        ->and($asset->responsible_department_id)->toBe($this->to->id)
        ->and($asset->location_id)->toBe($this->room->id)
        ->and($asset->custodian_officer_id)->toBe($this->receiver->officer_id)
        ->and($asset->qr_token)->toBe($this->asset['qr_token']);

    expect(AssetCustodyHistory::where('asset_id', $asset->id)->count())->toBe(3);
});

it('does not let the requester approve their own transfer', function () {
    $both = $this->officerUser([['chief_clerk', 'department', $this->from->id], 'accountant_supply'], $this->from);
    $this->actingAsUser($both);
    $transfer = $this->postJson('/api/v1/asset-transfers', ['asset_id' => $this->asset['public_id'], 'to_department_id' => $this->to->id])->assertCreated()->json('data');

    $this->assertApiError($this->postJson("/api/v1/asset-transfers/{$transfer['public_id']}/approve"), 422, 'BUSINESS_RULE_VIOLATION');
});

it('keeps old coverage on warranty extension and closes repairs only after a passed test (acceptance: warranty and repair)', function () {
    $this->actingAsUser($this->tech);
    $warranty = $this->postJson('/api/v1/warranties', [
        'asset_id' => $this->asset['public_id'],
        'starts_on' => now()->subYear()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/warranties/{$warranty['id']}/extend", ['ends_on' => now()->addYears(2)->toDateString()])->assertCreated();
    expect(Warranty::find($warranty['id'])->ends_on->toDateString())->toBe(now()->addMonth()->toDateString())
        ->and(Warranty::where('extends_warranty_id', $warranty['id'])->count())->toBe(1);

    $case = $this->postJson('/api/v1/repair-cases', ['asset_id' => $this->asset['public_id'], 'fault_description' => 'Screen flickers'])->assertCreated()->json('data');
    expect($case['under_warranty'])->toBeTrue();

    // An asset under repair cannot also be transferred.
    $this->actingAsUser($this->clerk);
    $this->assertApiError($this->postJson('/api/v1/asset-transfers', ['asset_id' => $this->asset['public_id'], 'to_department_id' => $this->to->id]), 422, 'BUSINESS_RULE_VIOLATION');

    $this->actingAsUser($this->tech);
    $id = $case['public_id'];
    $this->postJson("/api/v1/repair-cases/{$id}/assess", ['assessment' => 'Display cable'])->assertOk()->assertJsonPath('data.status', 'approved'); // warranty: no expenditure approval
    $this->postJson("/api/v1/repair-cases/{$id}/dispatch")->assertOk();

    $asset = Asset::find($this->asset['id']);
    expect($asset->operational_status)->toBe('under_repair')->and($asset->responsible_department_id)->toBe($this->from->id);

    $this->postJson("/api/v1/repair-cases/{$id}/return")->assertOk();
    $this->postJson("/api/v1/repair-cases/{$id}/test", ['passed' => false])->assertOk();
    $this->assertApiError($this->postJson("/api/v1/repair-cases/{$id}/close"), 422, 'BUSINESS_RULE_VIOLATION');

    $this->postJson("/api/v1/repair-cases/{$id}/return")->assertOk();
    $this->postJson("/api/v1/repair-cases/{$id}/test", ['passed' => true])->assertOk();
    $this->postJson("/api/v1/repair-cases/{$id}/close", ['outcome' => 'Cable replaced'])->assertOk()->assertJsonPath('data.status', 'closed');

    expect(Asset::find($this->asset['id'])->operational_status)->toBe('in_use')
        // the repair did not restart or extend the warranty
        ->and(Warranty::where('asset_id', $this->asset['id'])->count())->toBe(2);
});

it('values and disposes of an asset with independent approval', function () {
    $assessor = $this->officerUser(['cfo']);
    $assessorManage = $this->officerUser(['storekeeper']);
    UserPermission::create(['user_id' => $assessorManage->id, 'permission_id' => Permission::where('code', 'valuation.manage')->value('id'), 'effect' => 'allow', 'scope_type' => 'global', 'valid_from' => now()->subMinute(), 'reason' => 'test']);
    UserPermission::create(['user_id' => $assessorManage->id, 'permission_id' => Permission::where('code', 'disposal.manage')->value('id'), 'effect' => 'allow', 'scope_type' => 'global', 'valid_from' => now()->subMinute(), 'reason' => 'test']);
    $assessorManage->bumpPermissionVersion();

    $this->actingAsUser($assessorManage->fresh());
    $valuation = $this->postJson('/api/v1/valuations', ['asset_id' => $this->asset['public_id'], 'new_value' => 150000, 'effective_on' => now()->toDateString(), 'decision_ref' => 'BOS/2026/9'])->assertCreated()->json('data');

    $this->actingAsUser($assessor);
    $this->postJson("/api/v1/valuations/{$valuation['id']}/decide", ['approve' => true])->assertOk()->assertJsonPath('data.status', 'approved');

    $this->actingAsUser($assessorManage->fresh());
    $disposal = $this->postJson('/api/v1/disposals', ['asset_id' => $this->asset['public_id'], 'method' => 'auction', 'decision_ref' => 'BOS/2026/10'])->assertCreated()->json('data');
    $this->assertApiError($this->postJson("/api/v1/disposals/{$disposal['id']}/complete"), 422, 'BUSINESS_RULE_VIOLATION');

    $this->actingAsUser($assessor);
    $this->postJson("/api/v1/disposals/{$disposal['id']}/decide", ['approve' => true])->assertOk();
    $this->actingAsUser($assessorManage->fresh());
    $this->postJson("/api/v1/disposals/{$disposal['id']}/complete", ['outcome' => 'Sold at auction'])->assertOk();

    expect(Asset::find($this->asset['id'])->operational_status)->toBe('disposed');
});

it('records verification results and marks missing assets as lost', function () {
    $auditor = $this->officerUser(['storekeeper']);
    UserPermission::create(['user_id' => $auditor->id, 'permission_id' => Permission::where('code', 'verification.perform')->value('id'), 'effect' => 'allow', 'scope_type' => 'global', 'valid_from' => now()->subMinute(), 'reason' => 'test']);
    $auditor->bumpPermissionVersion();
    $this->actingAsUser($auditor->fresh());

    $session = $this->postJson('/api/v1/asset-verifications', ['scope_note' => 'Annual board of survey'])->assertCreated()->json('data');
    $this->postJson("/api/v1/asset-verifications/{$session['id']}/verify", ['asset_id' => $this->asset['public_id'], 'result' => 'missing', 'follow_up' => 'Inquire with department'])->assertCreated();

    expect(Asset::find($this->asset['id'])->operational_status)->toBe('lost');
});
