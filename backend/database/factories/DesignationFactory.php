<?php

namespace Database\Factories;

use App\Enums\DesignationCategory;
use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Designation>
 */
class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DSG-####')),
            'name' => fake()->jobTitle(),
            'category' => DesignationCategory::Clerical,
            'is_head_post' => false,
        ];
    }
}
