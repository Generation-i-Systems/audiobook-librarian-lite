<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBookStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserBookStatusFactory extends Factory
{
    protected $model = UserBookStatus::class;

    private const VALID_STATUSES = ['queue', 'wishlist', 'in_progress', 'completed'];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'status' => $this->faker->randomElement(self::VALID_STATUSES),
            'order' => $this->faker->unique()->numberBetween(1, 100),
            'read_count' => $this->faker->numberBetween(0, 5),
            'status_detail' => ['note' => $this->faker->sentence()],
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
