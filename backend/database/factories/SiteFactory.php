<?php

namespace Database\Factories;

use App\Enums\SiteType;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SITE-###')),
            'name' => fake()->company().' Office',
            'site_type' => SiteType::MainOffice,
        ];
    }
}
