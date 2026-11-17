<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum SiteType: string
{
    use EnumValues;

    case MainOffice = 'main_office';
    case Bungalow = 'bungalow';
    case RelatedInstitution = 'related_institution';
    case Warehouse = 'warehouse';
    case Other = 'other';
}
