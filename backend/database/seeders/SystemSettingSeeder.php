<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Services\System\SettingsService;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingsService::DEFINITIONS as $key => [$group, $type, $default, $description]) {
            SystemSetting::firstOrCreate(['key' => $key], [
                'group_key' => $group,
                'value_type' => $type,
                'value' => $type->serialize($default),
                'description' => $description,
            ]);
        }
    }
}
