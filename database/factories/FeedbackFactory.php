<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reservation_id' => null,
            'food_rating' => $this->faker->numberBetween(1, 5),
            'service_rating' => $this->faker->numberBetween(1, 5),
            'experience_rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional()->sentence(),
        ];
    }
}
