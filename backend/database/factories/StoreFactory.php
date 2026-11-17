<?php

namespace Database\Factories;

use App\Enums\StoreType;
use App\Models\Site;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('ST-###')),
            'name' => fake()->words(2, true).' Store',
            'site_id' => Site::factory(),
            'store_type' => StoreType::General,
        ];
    }
}
