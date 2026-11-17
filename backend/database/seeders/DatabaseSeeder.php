<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            OrganisationSeeder::class,
            SystemSettingSeeder::class,
            WorkflowSeeder::class,
            CatalogueReferenceSeeder::class,
            BootstrapAdminSeeder::class,
        ]);
    }
}
