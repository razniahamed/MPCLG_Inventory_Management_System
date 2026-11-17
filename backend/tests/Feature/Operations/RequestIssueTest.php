<?php

use App\Models\Department;
use App\Models\Discrepancy;
use App\Models\GoodsRequest;
use App\Models\StockBalance;
use App\Models\Store;
use App\Services\Stock\LedgerService;
use Tests\Support\BuildsOperations;

uses(BuildsOperations::class);

beforeEach(function () {
    $this->store = Store::factory()->create();
    $this->department = Department::factory()->create();
    $this->item = $this->makeItem();
    $this->stockUp($this->item, $this->store, 20);

    $this->clerk = $this->officerUser([['chief_clerk', 'department', $this->department->id]], $this->department);
    $this->head = $this->officerUser([['department_head', 'department', $this->department->id]], $this->department);
    $this->approver = $this->officerUser(['accountant_supply']);
    $this->storekeeper = $this->officerUser([['storekeeper', 'store', $this->store->id]]);
});

function approvedRequest($test, int $requested = 10, int $approve = 8): array
{
    $test->actingAsUser($test->clerk);
    $request = $test->postJson('/api/v1/requests', [
        'department_id' => $test->department->id,
        'store_id' => $test->store->id,
        'purpose' => 'Monthly stationery',
        'lines' => [['item_id' => $test->item->id, 'requested_qty' => $requested]],
    ])->assertCreated()->json('data');
    $test->postJson("/api/v1/requests/{$request['public_id']}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

    $test->actingAsUser($test->head);
    $test->postJson("/api/v1/requests/{$request['public_id']}/certify")->assertOk()->assertJsonPath('data.status', 'under_review');

    $test->actingAsUser($test->approver);

    return $test->postJson("/api/v1/requests/{$request['public_id']}/approve", [
        'lines' => [['id' => $request['lines'][0]['id'], 'approved_qty' => $approve]],
        'remarks' => $approve < $requested ? 'Reduced to monthly allowance' : null,
    ])->assertOk()->assertJsonPath('data.status', 'approved')->json('data');
}

function issue($test, array $request, $qty): array
{
    $test->actingAsUser($test->storekeeper);

    return $test->postJson('/api/v1/issues', [
        'request_id' => $request['public_id'],
        'lines' => [['request_line_id' => $request['lines'][0]['id'], 'qty' => $qty]],
    ])->assertCreated()->json('data');
}

it('does not touch stock on submit, reserves on approval, and deducts only on posting (rule 1)', function () {
    $request = approvedRequest($this);

    $balance = StockBalance::where(['item_id' => $this->item->id, 'store_id' => $this->store->id])->first();
    expect($balance->on_hand_usable)->toBe('20.000')->and($balance->reserved)->toBe('8.000');

    $draft = issue($this, $request, 5);
    expect(StockBalance::where('item_id', $this->item->id)->value('on_hand_usable'))->toBe('20.000');

    $this->postJson("/api/v1/issues/{$draft['public_id']}/post", [], $this->key())->assertOk();
    $balance->refresh();
    expect($balance->on_hand_usable)->toBe('15.000')->and($balance->reserved)->toBe('3.000');
});

it('allows 8 approved to be issued as 5 then 3 and blocks a 9th (acceptance: 8 → 5 + 3)', function () {
    $request = approvedRequest($this);

    $first = issue($this, $request, 5);
    $this->postJson("/api/v1/issues/{$first['public_id']}/post", [], $this->key())->assertOk();
    expect(GoodsRequest::where('public_id', $request['public_id'])->value('status'))->toBe('partly_issued');

    $second = issue($this, $request, 3);
    $this->postJson("/api/v1/issues/{$second['public_id']}/post", [], $this->key())->assertOk();

    $after = $this->getJson("/api/v1/requests/{$request['public_id']}")->assertOk()->json('data');
    expect($after['status'])->toBe('fully_issued')
        ->and($after['lines'][0]['outstanding_qty'])->toBe('0.000');

    $this->assertApiError($this->postJson('/api/v1/issues', [
        'request_id' => $request['public_id'],
        'lines' => [['request_line_id' => $request['lines'][0]['id'], 'qty' => 1]],
    ]), 422, 'BUSINESS_RULE_VIOLATION');

    expect(StockBalance::where('item_id', $this->item->id)->value('reserved'))->toBe('0.000');
    expect(app(LedgerService::class)->verify($this->item->id, $this->store->id))->toBeTrue();
});

it('serialises competing issues so the approved quantity is never exceeded (acceptance: concurrent issue)', function () {
    $request = approvedRequest($this);

    $a = issue($this, $request, 5);
    $b = issue($this, $request, 5); // both prepared before either is posted

    $this->postJson("/api/v1/issues/{$a['public_id']}/post", [], $this->key())->assertOk();
    $this->assertApiError($this->postJson("/api/v1/issues/{$b['public_id']}/post", [], $this->key()), 422, 'BUSINESS_RULE_VIOLATION')
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'exceed the approved'));

    expect(StockBalance::where('item_id', $this->item->id)->value('on_hand_usable'))->toBe('15.000');
});

it('is idempotent when the same posting is repeated', function () {
    $request = approvedRequest($this);
    $draft = issue($this, $request, 5);
    $headers = $this->key();

    $this->postJson("/api/v1/issues/{$draft['public_id']}/post", [], $headers)->assertOk();
    $this->postJson("/api/v1/issues/{$draft['public_id']}/post", [], $headers)->assertOk();

    expect(StockBalance::where('item_id', $this->item->id)->value('on_hand_usable'))->toBe('15.000');
});

it('keeps issue and receipt separate and opens a discrepancy on shortfall (acceptance: receipt discrepancy)', function () {
    $request = approvedRequest($this);
    $draft = issue($this, $request, 5);
    $this->postJson("/api/v1/issues/{$draft['public_id']}/post", [], $this->key())->assertOk();

    $this->actingAsUser($this->clerk);
    $this->postJson("/api/v1/issues/{$draft['public_id']}/acknowledge", [
        'status' => 'discrepancy',
        'remarks' => 'One packet missing',
        'lines' => [['issue_line_id' => $draft['lines'][0]['id'], 'received_qty' => 4]],
    ])->assertOk();

    expect(Discrepancy::where('status', 'open')->count())->toBe(1)
        ->and(GoodsRequest::where('public_id', $request['public_id'])->value('receipt_status'))->toBe('discrepancy')
        // stock is NOT silently adjusted
        ->and(StockBalance::where('item_id', $this->item->id)->value('on_hand_usable'))->toBe('15.000');
});

it('enforces no self-approval, mandatory reduce remarks and department scope', function () {
    $other = Department::factory()->create();

    $this->actingAsUser($this->clerk);
    $this->assertApiError($this->postJson('/api/v1/requests', [
        'department_id' => $other->id, 'store_id' => $this->store->id,
        'lines' => [['item_id' => $this->item->id, 'requested_qty' => 1]],
    ]), 403, 'PERMISSION_DENIED');

    $request = $this->postJson('/api/v1/requests', [
        'department_id' => $this->department->id, 'store_id' => $this->store->id,
        'lines' => [['item_id' => $this->item->id, 'requested_qty' => 10]],
    ])->assertCreated()->json('data');
    $this->postJson("/api/v1/requests/{$request['public_id']}/submit")->assertOk();

    // A head who submitted it cannot certify it.
    GoodsRequest::where('public_id', $request['public_id'])->update(['submitted_by_officer_id' => $this->head->officer_id]);
    $this->actingAsUser($this->head);
    $this->assertApiError($this->postJson("/api/v1/requests/{$request['public_id']}/certify"), 422, 'BUSINESS_RULE_VIOLATION');
    GoodsRequest::where('public_id', $request['public_id'])->update(['submitted_by_officer_id' => $this->clerk->officer_id]);
    $this->postJson("/api/v1/requests/{$request['public_id']}/certify")->assertOk();

    $this->actingAsUser($this->approver);
    $this->assertApiError($this->postJson("/api/v1/requests/{$request['public_id']}/approve", [
        'lines' => [['id' => $request['lines'][0]['id'], 'approved_qty' => 6]],
    ]), 422, 'VALIDATION_ERROR');
});

it('versions a returned request and restarts approval (rule 10)', function () {
    $this->actingAsUser($this->clerk);
    $request = $this->postJson('/api/v1/requests', [
        'department_id' => $this->department->id, 'store_id' => $this->store->id,
        'lines' => [['item_id' => $this->item->id, 'requested_qty' => 10]],
    ])->assertCreated()->json('data');
    $this->postJson("/api/v1/requests/{$request['public_id']}/submit")->assertOk();

    $this->actingAsUser($this->head);
    $this->postJson("/api/v1/requests/{$request['public_id']}/return", ['remarks' => 'Justify the quantity'])->assertOk()->assertJsonPath('data.status', 'returned');

    $this->actingAsUser($this->clerk);
    $revised = $this->patchJson("/api/v1/requests/{$request['public_id']}", [
        'lines' => [['item_id' => $this->item->id, 'requested_qty' => 6]],
    ])->assertOk()->json('data');
    expect($revised['current_version_no'])->toBe(2)->and($revised['lines'][0]['requested_qty'])->toBe('6.000');

    $this->postJson("/api/v1/requests/{$request['public_id']}/submit")->assertOk();
    $history = $this->getJson("/api/v1/requests/{$request['public_id']}")->json('data.approvals');
    expect(collect($history)->where('version_no', 1)->pluck('status')->all())->toContain('returned')
        ->and(collect($history)->where('version_no', 2)->pluck('status')->unique()->all())->toBe(['pending']);
});

it('reverses an issue and returns goods from a department (only accepted qty back)', function () {
    $request = approvedRequest($this);
    $draft = issue($this, $request, 8);
    $this->postJson("/api/v1/issues/{$draft['public_id']}/post", [], $this->key())->assertOk();

    $this->actingAsUser($this->clerk);
    $return = $this->postJson('/api/v1/department-returns', [
        'issue_id' => $draft['id'],
        'reason' => 'Over-supplied',
        'lines' => [['issue_line_id' => $draft['lines'][0]['id'], 'qty_returned' => 3]],
    ])->assertCreated()->json('data');

    $this->actingAsUser($this->storekeeper);
    $detail = $this->getJson("/api/v1/department-returns/{$return['public_id']}")->json('data');
    $this->postJson("/api/v1/department-returns/{$return['public_id']}/inspect", ['lines' => [['id' => $detail['lines'][0]['id'], 'qty_accepted' => 2, 'condition' => 'damaged']]])->assertOk();
    $this->postJson("/api/v1/department-returns/{$return['public_id']}/post", [], $this->key())->assertOk();

    expect(StockBalance::where('item_id', $this->item->id)->value('on_hand_usable'))->toBe('14.000');
});
