<?php

use App\Models\Grn;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Store;
use App\Services\Stock\LedgerService;
use Tests\Support\BuildsOperations;

uses(BuildsOperations::class);

beforeEach(function () {
    $this->store = Store::factory()->create();
    $this->item = $this->makeItem();
    $this->supplier = $this->makeSupplier();
    $this->storekeeper = $this->officerUser([['storekeeper', 'store', $this->store->id]]);
    $this->inspector = $this->officerUser(['receiving_officer']);
    $this->approver = $this->officerUser(['chief_accountant']);
    $this->clerk = $this->officerUser(['accountant_supply']);

    $this->actingAsUser($this->clerk);
    $po = $this->postJson('/api/v1/purchase-orders', [
        'po_number' => 'PO-2026-001',
        'po_date' => now()->toDateString(),
        'supplier_id' => $this->supplier->id,
        'lines' => [['item_id' => $this->item->id, 'unit_id' => $this->item->base_unit_id, 'ordered_qty' => 100, 'unit_price' => 250]],
    ])->assertCreated()->json('data');
    $this->po = PurchaseOrder::find($po['id']);
});

function receive($test, int $delivered, string $invoice = 'INV-1'): array
{
    $test->actingAsUser($test->storekeeper);
    $grn = $test->postJson('/api/v1/grns', [
        'receipt_authority_type' => 'po',
        'purchase_order_id' => $test->po->id,
        'store_id' => $test->store->id,
        'receipt_on' => now()->toDateString(),
        'invoice_no' => $invoice,
        'lines' => [['item_id' => $test->item->id, 'unit_id' => $test->item->base_unit_id, 'po_line_id' => $test->po->lines()->first()->id, 'delivered_qty' => $delivered, 'unit_price' => 250]],
    ])->assertCreated()->json('data');
    $test->postJson("/api/v1/grns/{$grn['public_id']}/submit")->assertOk();

    return $grn;
}

it('posts only accepted stock from a partial GRN and keeps the PO outstanding (acceptance: partial GRN and rejected stock)', function () {
    $grn = receive($this, 60);

    $this->actingAsUser($this->inspector);
    $this->postJson("/api/v1/grns/{$grn['public_id']}/inspect", ['lines' => [[
        'id' => $grn['lines'][0]['id'], 'accepted_qty' => 50, 'rejected_qty' => 10, 'rejection_reason' => 'Damaged boxes',
    ]]])->assertOk()->assertJsonPath('data.status', 'awaiting_approval');

    // Nothing is usable before approval.
    expect(StockBalance::where('item_id', $this->item->id)->sum('on_hand_usable'))->toEqual(0);

    $this->actingAsUser($this->approver);
    $headers = $this->key();
    $this->postJson("/api/v1/grns/{$grn['public_id']}/approve-post", [], $headers)->assertOk()->assertJsonPath('data.status', 'posted');

    expect(app(LedgerService::class)->available($this->item->id, $this->store->id))->toBe('50.000');
    expect($this->po->fresh()->status)->toBe('partly_received');
    expect($this->po->lines()->first()->outstandingQty())->toBe('50.000');

    // Re-posting with the same key adds nothing.
    $this->postJson("/api/v1/grns/{$grn['public_id']}/approve-post", [], $headers)->assertOk();
    expect(StockLedger::where('source_type', 'grns')->count())->toBe(1);

    // A different key on an already posted GRN is a duplicate posting.
    $this->assertApiError($this->postJson("/api/v1/grns/{$grn['public_id']}/approve-post", [], $this->key()), 409, 'DUPLICATE_POSTING');
    expect(app(LedgerService::class)->verify($this->item->id, $this->store->id))->toBeTrue();
});

it('requires an Idempotency-Key and an independent approver', function () {
    $grn = receive($this, 10);
    $this->actingAsUser($this->inspector);
    $this->postJson("/api/v1/grns/{$grn['public_id']}/inspect", ['lines' => [['id' => $grn['lines'][0]['id'], 'accepted_qty' => 10]]])->assertOk();

    $this->actingAsUser($this->approver);
    $this->assertApiError($this->postJson("/api/v1/grns/{$grn['public_id']}/approve-post"), 422, 'VALIDATION_ERROR');

    // The officer who received the goods cannot approve them, even with the permission.
    Grn::where('public_id', $grn['public_id'])->update(['received_by_officer_id' => $this->approver->officer_id]);
    $this->assertApiError($this->postJson("/api/v1/grns/{$grn['public_id']}/approve-post", [], $this->key()), 422, 'BUSINESS_RULE_VIOLATION')
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'self-approval'));
});

it('rejects duplicate supplier invoices and quantities above the PO', function () {
    receive($this, 10, 'INV-DUP');

    $this->actingAsUser($this->storekeeper);
    $this->assertApiError($this->postJson('/api/v1/grns', [
        'receipt_authority_type' => 'po',
        'purchase_order_id' => $this->po->id,
        'store_id' => $this->store->id,
        'receipt_on' => now()->toDateString(),
        'invoice_no' => 'INV-DUP',
        'lines' => [['item_id' => $this->item->id, 'unit_id' => $this->item->base_unit_id, 'po_line_id' => $this->po->lines()->first()->id, 'delivered_qty' => 1]],
    ]), 409, 'DUPLICATE_POSTING');

    $grn = receive($this, 150, 'INV-BIG');
    $this->actingAsUser($this->inspector);
    $this->postJson("/api/v1/grns/{$grn['public_id']}/inspect", ['lines' => [['id' => $grn['lines'][0]['id'], 'accepted_qty' => 150]]])->assertOk();
    $this->actingAsUser($this->approver);
    $this->assertApiError($this->postJson("/api/v1/grns/{$grn['public_id']}/approve-post", [], $this->key()), 422, 'BUSINESS_RULE_VIOLATION')
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'outstanding'));
});

it('limits storekeepers to their own store', function () {
    $other = Store::factory()->create();
    $this->actingAsUser($this->storekeeper);

    $this->assertApiError($this->postJson('/api/v1/grns', [
        'receipt_authority_type' => 'donation',
        'receipt_authority_ref' => 'DON/1',
        'store_id' => $other->id,
        'receipt_on' => now()->toDateString(),
        'lines' => [['item_id' => $this->item->id, 'unit_id' => $this->item->base_unit_id, 'delivered_qty' => 1]],
    ]), 403, 'PERMISSION_DENIED');
});

it('reverses a posted GRN with linked ledger entries', function () {
    $grn = receive($this, 20);
    $this->actingAsUser($this->inspector);
    $this->postJson("/api/v1/grns/{$grn['public_id']}/inspect", ['lines' => [['id' => $grn['lines'][0]['id'], 'accepted_qty' => 20]]])->assertOk();
    $this->actingAsUser($this->approver);
    $this->postJson("/api/v1/grns/{$grn['public_id']}/approve-post", [], $this->key())->assertOk();
    $this->postJson("/api/v1/grns/{$grn['public_id']}/reverse", ['reason' => 'Posted to wrong store'])->assertOk()->assertJsonPath('data.status', 'reversed');

    expect(app(LedgerService::class)->available($this->item->id, $this->store->id))->toBe('0.000')
        ->and(StockLedger::where('source_type', 'grns')->count())->toBe(2)
        ->and(StockLedger::whereNotNull('reverses_ledger_id')->count())->toBe(1)
        ->and($this->po->fresh()->status)->toBe('open');
});

it('counts stock and turns a confirmed variance into an independently approved adjustment', function () {
    $this->stockUp($this->item, $this->store, 40);

    $this->actingAsUser($this->storekeeper);
    $session = $this->postJson('/api/v1/stock-counts', ['store_id' => $this->store->id])->assertCreated()->json('data');
    $line = collect($session['lines'])->firstWhere('item_id', $this->item->id);
    expect($line['expected_qty'])->toBe('40.000');

    $this->postJson("/api/v1/stock-counts/{$session['public_id']}/record", ['lines' => [['id' => $line['id'], 'counted_qty' => 37]]])->assertOk()->assertJsonPath('data.status', 'recount');
    $this->assertApiError($this->postJson("/api/v1/stock-counts/{$session['public_id']}/submit"), 422, 'BUSINESS_RULE_VIOLATION');
    $this->postJson("/api/v1/stock-counts/{$session['public_id']}/record", ['lines' => [['id' => $line['id'], 'recount_qty' => 38, 'remarks' => 'Two packs found broken']]])->assertOk();
    $this->postJson("/api/v1/stock-counts/{$session['public_id']}/submit")->assertOk();

    $this->actingAsUser($this->approver);
    $this->postJson("/api/v1/stock-counts/{$session['public_id']}/approve")->assertOk();

    $adjustment = $this->getJson('/api/v1/stock-adjustments?filter[status]=submitted')->assertOk()->json('data.0');
    expect($adjustment['qty_delta'])->toBe('-2.000');

    // The counter's own officer cannot approve; an independent approver posts it.
    $this->actingAsUser($this->storekeeper);
    $this->assertApiError($this->postJson("/api/v1/stock-adjustments/{$adjustment['public_id']}/approve", [], $this->key()), 403, 'PERMISSION_DENIED');
    $this->actingAsUser($this->approver);
    $this->postJson("/api/v1/stock-adjustments/{$adjustment['public_id']}/approve", [], $this->key())->assertOk()->assertJsonPath('data.status', 'posted');

    expect(app(LedgerService::class)->available($this->item->id, $this->store->id))->toBe('38.000');
});
