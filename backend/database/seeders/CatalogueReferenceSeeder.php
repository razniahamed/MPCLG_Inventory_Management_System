<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/** Standard units and top-level item categories. Idempotent. */
class CatalogueReferenceSeeder extends Seeder
{
    private const UNITS = [
        'PCS' => ['Piece', 'count'],
        'SHT' => ['Sheet', 'count'],
        'REAM' => ['Ream', 'pack'],
        'BOX' => ['Box', 'pack'],
        'PKT' => ['Packet', 'pack'],
        'SET' => ['Set', 'count'],
        'ROLL' => ['Roll', 'count'],
        'BTL' => ['Bottle', 'count'],
        'KG' => ['Kilogram', 'weight'],
        'L' => ['Litre', 'volume'],
        'M' => ['Metre', 'length'],
    ];

    private const CATEGORIES = [
        'STAT' => ['Stationery', 'quantity'],
        'PAPER' => ['Paper', 'quantity'],
        'TONER' => ['Toner & ink', 'quantity'],
        'CLEAN' => ['Cleaning supplies', 'quantity'],
        'ITEQ' => ['IT equipment', 'asset'],
        'OFFEQ' => ['Office equipment', 'asset'],
        'FURN' => ['Furniture', 'asset'],
    ];

    public function run(): void
    {
        foreach (self::UNITS as $code => [$name, $kind]) {
            Unit::updateOrCreate(['code' => $code], ['name' => $name, 'kind' => $kind]);
        }

        foreach (self::CATEGORIES as $code => [$name, $tracking]) {
            ItemCategory::updateOrCreate(['code' => $code], ['name' => $name, 'default_tracking_method' => $tracking]);
        }
    }
}
