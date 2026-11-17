<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DEP-####')),
            'name_en' => fake()->unique()->words(2, true).' Unit',
            'division_id' => Division::factory(),
            'active_from' => '2026-01-01',
        ];
    }
}
