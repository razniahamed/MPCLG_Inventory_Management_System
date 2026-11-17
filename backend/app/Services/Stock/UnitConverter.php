<?php

namespace App\Services\Stock;

use App\Exceptions\ApiException;
use App\Models\Item;
use App\Models\ItemUnitConversion;
use App\Support\Dec;

/**
 * The ledger always stores the item's base unit. Other units need an APPROVED
 * per-item conversion; sheets per ream etc. are never assumed (rule 17).
 */
class UnitConverter
{
    public function toBase(Item $item, int $unitId, mixed $qty): string
    {
        if ($unitId === (int) $item->base_unit_id) {
            return Dec::n($qty);
        }

        $direct = ItemUnitConversion::query()
            ->where(['item_id' => $item->id, 'from_unit_id' => $unitId, 'to_unit_id' => $item->base_unit_id])
            ->whereNotNull('approved_at')
            ->first();
        if ($direct) {
            return Dec::mul($qty, $direct->factor);
        }

        $inverse = ItemUnitConversion::query()
            ->where(['item_id' => $item->id, 'from_unit_id' => $item->base_unit_id, 'to_unit_id' => $unitId])
            ->whereNotNull('approved_at')
            ->first();
        if ($inverse) {
            return bcdiv(Dec::n($qty, 6), Dec::n($inverse->factor, 6), Dec::QTY);
        }

        throw ApiException::businessRule("No approved unit conversion for {$item->item_code} into its base unit. Approve one under Items → Conversions first.");
    }

    /** Units an item can be recorded in: its base unit plus approved conversions. */
    public function allowedUnitIds(Item $item): array
    {
        $ids = [(int) $item->base_unit_id];
        foreach (ItemUnitConversion::where('item_id', $item->id)->whereNotNull('approved_at')->get() as $c) {
            $ids[] = (int) $c->from_unit_id;
            $ids[] = (int) $c->to_unit_id;
        }

        return array_values(array_unique($ids));
    }
}
