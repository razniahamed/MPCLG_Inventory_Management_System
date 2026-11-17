<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data for the test suite (no bootstrap admin: tests create their own users).
 */
class TestingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            OrganisationSeeder::class,
            SystemSettingSeeder::class,
        ]);
    }
}
