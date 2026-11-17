<?php

namespace App\Services\Catalogue;

use App\Enums\AuditAction;
use App\Exceptions\ApiException;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnitConversion;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Organisation\MasterDataService;
use App\Support\Dec;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Items, suppliers and approved unit conversions (Phase 2), with duplicate checks.
 */
class CatalogueService
{
    public function __construct(
        private readonly MasterDataService $masterData,
        private readonly AuditLogger $audit,
    ) {}

    public function validateItem(Item $item): void
    {
        $category = ItemCategory::find($item->category_id);
        if ($category && ! $item->tracking_method) {
            $item->tracking_method = $category->default_tracking_method;
        }

        $duplicate = Item::query()
            ->whereRaw('LOWER(name_en) = ?', [mb_strtolower(trim((string) $item->name_en))])
            ->where('category_id', $item->category_id)
            ->when($item->exists, fn ($q) => $q->whereKeyNot($item->id))
            ->first();
        if ($duplicate) {
            throw ApiException::businessRule("An item with this name already exists in the category ({$duplicate->item_code}).", [
                'name_en' => ["Duplicate of {$duplicate->item_code}."],
            ]);
        }

        if ($item->exists && $item->isDirty(['tracking_method', 'base_unit_id']) && StockLedger::where('item_id', $item->id)->exists()) {
            throw ApiException::businessRule('The tracking method and base unit cannot change once stock has been posted for the item.');
        }
    }

    /** @param  array<string, mixed>  $data */
    public function saveSupplier(?Supplier $supplier, array $data): Supplier
    {
        $categories = Arr::pull($data, 'category_ids');

        $name = mb_strtolower(trim((string) ($data['name'] ?? $supplier?->name)));
        $taxRef = $data['tax_ref'] ?? $supplier?->tax_ref;

        $duplicate = Supplier::query()
            ->where(function ($q) use ($name, $taxRef) {
                $q->whereRaw('LOWER(name) = ?', [$name]);
                if ($taxRef) {
                    $q->orWhere('tax_ref', $taxRef);
                }
            })
            ->when($supplier, fn ($q) => $q->whereKeyNot($supplier->id))
            ->first();

        if ($duplicate) {
            throw ApiException::businessRule("Possible duplicate supplier: {$duplicate->code} — {$duplicate->name} (same name or tax reference).", [
                'name' => ["Duplicate of {$duplicate->code}."],
            ]);
        }

        return DB::transaction(function () use ($supplier, $data, $categories) {
            $saved = $supplier
                ? $this->masterData->update($supplier, $data)
                : $this->masterData->create(Supplier::class, $data);

            if (is_array($categories)) {
                $saved->categories()->sync($categories);
            }

            return $saved->load('categories');
        });
    }

    public function addConversion(Item $item, array $data): ItemUnitConversion
    {
        if ((int) $data['from_unit_id'] === (int) $data['to_unit_id']) {
            throw ApiException::businessRule('Choose two different units.');
        }

        return DB::transaction(function () use ($item, $data) {
            $conversion = ItemUnitConversion::create([
                'item_id' => $item->id,
                'from_unit_id' => $data['from_unit_id'],
                'to_unit_id' => $data['to_unit_id'],
                'factor' => $data['factor'],
            ]);
            $this->audit->log(AuditAction::Created, $conversion, null, $conversion->only(['item_id', 'from_unit_id', 'to_unit_id', 'factor']), $data['reason'] ?? null);

            return $conversion;
        });
    }

    /** A conversion only takes effect after a second person approves it. */
    public function approveConversion(ItemUnitConversion $conversion, ?string $reason): ItemUnitConversion
    {
        if ($conversion->approved_at) {
            throw ApiException::businessRule('This conversion is already approved.');
        }
        if ($conversion->created_by === Auth::id()) {
            throw ApiException::businessRule('A conversion must be approved by someone other than the person who entered it.');
        }

        return DB::transaction(function () use ($conversion, $reason) {
            $conversion->update(['approved_by' => Auth::id(), 'approved_at' => now()]);
            $this->audit->log(AuditAction::Approved, $conversion, null, ['factor' => $conversion->factor], $reason);

            return $conversion;
        });
    }

    /** Current stock per store for an item (for the item page). */
    public function stockByStore(Item $item): array
    {
        return StockBalance::query()
            ->where('item_id', $item->id)
            ->with('store:id,code,name')
            ->get()
            ->groupBy('store_id')
            ->map(fn ($rows) => [
                'store' => $rows->first()->store?->only(['id', 'code', 'name']),
                'on_hand_usable' => Dec::sum($rows->pluck('on_hand_usable')),
                'reserved' => Dec::sum($rows->pluck('reserved')),
            ])->values()->all();
    }
}
