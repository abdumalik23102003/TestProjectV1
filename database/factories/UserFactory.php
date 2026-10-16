<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'telegram_user_id' => fake()->unique()->numberBetween(100_000_000, 9_999_999_999),
            'full_name' => fake()->name(),
            'role' => 'student',
            'status' => 'active',
        ];
    }

    public function teacher(): static
    {
        return $this->state(['role' => 'teacher']);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function blocked(): static
    {
        return $this->state(['status' => 'blocked']);
    }
}
