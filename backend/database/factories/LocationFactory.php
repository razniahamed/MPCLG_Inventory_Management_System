<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'location_type' => LocationType::Room,
            'code' => strtoupper(fake()->unique()->bothify('RM-###')),
            'name' => 'Room '.fake()->numberBetween(1, 500),
        ];
    }
}
