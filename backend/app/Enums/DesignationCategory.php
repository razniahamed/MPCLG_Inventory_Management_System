<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum DesignationCategory: string
{
    use EnumValues;

    case Executive = 'executive';
    case Professional = 'professional';
    case Clerical = 'clerical';
    case Technical = 'technical';
    case Minor = 'minor';
    case Contract = 'contract';
}
