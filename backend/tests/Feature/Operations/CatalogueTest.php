<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Services\Stock\UnitConverter;
use Tests\Support\BuildsOperations;

uses(BuildsOperations::class);

beforeEach(function () {
    $this->manager = $this->officerUser(['accountant_supply']);
    $this->actingAsUser($this->manager);
});

it('creates items with units and categories and rejects duplicates', function () {
    $paper = ItemCategory::where('code', 'PAPER')->first();
    $ream = $this->unit('REAM');

    $payload = [
        'item_code' => 'PAP-A4-80',
        'name_en' => 'A4 paper 80gsm',
        'category_id' => $paper->id,
        'tracking_method' => 'quantity',
        'base_unit_id' => $this->unit('SHT')->id,
        'purchase_unit_id' => $ream->id,
        'issue_unit_id' => $ream->id,
        'reorder_level' => 5000,
        'is_paper' => true,
    ];

    $this->postJson('/api/v1/items', $payload)->assertCreated()->assertJsonPath('data.item_code', 'PAP-A4-80');

    // Same code → validation; same name in the same category → business rule.
    $this->assertApiError($this->postJson('/api/v1/items', $payload), 422, 'VALIDATION_ERROR');
    $this->assertApiError($this->postJson('/api/v1/items', ['item_code' => 'PAP-A4-80B'] + $payload), 422, 'BUSINESS_RULE_VIOLATION');

    $this->getJson('/api/v1/items?filter[is_paper]=true')->assertOk()->assertJsonPath('meta.total', 1);
});

it('flags duplicate suppliers by name or tax reference', function () {
    $this->postJson('/api/v1/suppliers', ['code' => 'S1', 'name' => 'Lanka Stationers', 'supplier_type' => 'goods', 'tax_ref' => 'VAT-1'])->assertCreated();

    $this->assertApiError($this->postJson('/api/v1/suppliers', ['code' => 'S2', 'name' => 'lanka stationers', 'supplier_type' => 'goods']), 422, 'BUSINESS_RULE_VIOLATION');
    $this->assertApiError($this->postJson('/api/v1/suppliers', ['code' => 'S3', 'name' => 'Other Co', 'supplier_type' => 'goods', 'tax_ref' => 'VAT-1']), 422, 'BUSINESS_RULE_VIOLATION');
});

it('requires a second person to approve a unit conversion (never assumed)', function () {
    $item = $this->makeItem(['base_unit_id' => $this->unit('SHT')->id]);

    $conversion = $this->postJson("/api/v1/items/{$item->id}/conversions", [
        'from_unit_id' => $this->unit('REAM')->id,
        'to_unit_id' => $this->unit('SHT')->id,
        'factor' => 500,
    ])->assertCreated()->json('data');

    $this->assertApiError($this->postJson("/api/v1/items/{$item->id}/conversions/{$conversion['id']}/approve"), 422, 'BUSINESS_RULE_VIOLATION');

    $this->actingAsUser($this->officerUser(['accountant_supply']));
    $this->postJson("/api/v1/items/{$item->id}/conversions/{$conversion['id']}/approve")->assertOk();

    expect(app(UnitConverter::class)->toBase(Item::find($item->id), $this->unit('REAM')->id, 2))->toBe('1000.000');
});

it('blocks catalogue changes for roles without the permission', function () {
    $this->actingAsUser($this->officerUser(['chief_clerk']));
    $this->getJson('/api/v1/items')->assertOk();
    $this->assertApiError($this->postJson('/api/v1/items', []), 403, 'PERMISSION_DENIED');
});
