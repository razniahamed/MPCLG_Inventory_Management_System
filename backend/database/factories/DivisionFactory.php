<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DIV-####')),
            'name_en' => fake()->unique()->words(3, true).' Division',
            'sort_order' => 0,
        ];
    }
}
