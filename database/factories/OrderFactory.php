<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.$this->faker->unique()->numerify('########'),
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'customer_phone' => $this->faker->numerify('01#########'),
            'order_type' => $this->faker->randomElement(['dine-in', 'takeaway']),
            'status' => 'pending',
            'total' => $this->faker->randomFloat(2, 20, 200),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }
}
