<?php

namespace App\Models;

use App\Enums\SettingValueType;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasAuditColumns;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value_type' => SettingValueType::class,
        ];
    }

    public function typedValue(): mixed
    {
        return $this->value_type->cast($this->value);
    }
}
