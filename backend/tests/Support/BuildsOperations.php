<?php

namespace Tests\Support;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Officer;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Stock\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Test data builders for the operational phases. */
trait BuildsOperations
{
    /** A user linked to an officer (business documents need a named officer). */
    protected function officerUser(array $roles, ?Department $department = null): User
    {
        $officer = Officer::factory()->create($department ? ['department_id' => $department->id] : []);

        return $this->userWithRoles($roles, ['officer_id' => $officer->id]);
    }

    protected function unit(string $code = 'PCS'): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    protected function makeItem(array $attributes = []): Item
    {
        $tracking = $attributes['tracking_method'] ?? 'quantity';
        $category = ItemCategory::where('code', $tracking === 'asset' ? 'ITEQ' : 'STAT')->firstOrFail();
        $unit = $this->unit();

        return Item::create($attributes + [
            'item_code' => 'IT-'.Str::upper(Str::random(6)),
            'name_en' => 'Test item '.Str::random(5),
            'category_id' => $category->id,
            'tracking_method' => $tracking,
            'base_unit_id' => $unit->id,
            'purchase_unit_id' => $unit->id,
            'issue_unit_id' => $unit->id,
        ]);
    }

    protected function makeSupplier(): Supplier
    {
        return Supplier::create([
            'code' => 'SUP-'.Str::upper(Str::random(5)),
            'name' => 'Supplier '.Str::random(6),
            'supplier_type' => 'goods',
        ]);
    }

    /** Put opening stock straight on the ledger (bypasses the GRN workflow). */
    protected function stockUp(Item $item, Store $store, string|int $qty): void
    {
        DB::transaction(function () use ($item, $store, $qty) {
            app(LedgerService::class)->post([
                'item_id' => $item->id,
                'store_id' => $store->id,
                'qty' => (string) $qty,
                'movement_type' => 'opening',
                'event_on' => now()->toDateString(),
                'source_type' => 'test',
                'source_id' => 0,
            ], Officer::factory()->create());
        });
    }

    protected function key(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }
}
