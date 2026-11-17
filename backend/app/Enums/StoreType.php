<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum StoreType: string
{
    use EnumValues;

    case General = 'general';
    case Stationery = 'stationery';
    case It = 'it';
    case Furniture = 'furniture';
    case Other = 'other';
}
