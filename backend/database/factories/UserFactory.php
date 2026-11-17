<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public const PASSWORD = 'Password@12345';

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName().fake()->numberBetween(10, 99),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::PASSWORD,
            'status' => UserStatus::Active,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Pending, 'must_change_password' => true]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Deactivated, 'deactivated_at' => now()]);
    }
}
