<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum AuditAction: string
{
    use EnumValues;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case StatusChanged = 'status_changed';
    case Approved = 'approved';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case PermissionChanged = 'permission_changed';
    case Exported = 'exported';
    case ViewedSensitive = 'viewed_sensitive';
}
