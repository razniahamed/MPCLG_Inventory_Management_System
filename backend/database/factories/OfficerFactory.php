<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Officer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Officer>
 */
class OfficerFactory extends Factory
{
    protected $model = Officer::class;

    public function definition(): array
    {
        return [
            'officer_no' => strtoupper(fake()->unique()->bothify('OFF-#####')),
            'full_name' => fake()->name(),
            'designation_id' => Designation::factory(),
            'department_id' => Department::factory(),
            'employment_status' => EmploymentStatus::Active,
            'joined_on' => '2020-01-01',
        ];
    }
}
