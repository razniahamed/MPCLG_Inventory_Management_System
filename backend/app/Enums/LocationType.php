<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum LocationType: string
{
    use EnumValues;

    case Room = 'room';
    case Shelf = 'shelf';
    case Rack = 'rack';
    case Cupboard = 'cupboard';
    case Other = 'other';
}
