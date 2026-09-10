<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_id' => Table::factory(),
            'reservation_number' => 'RES-'.$this->faker->unique()->numerify('########'),
            'reservation_type' => 'table_only',
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'customer_phone' => $this->faker->numerify('01#########'),
            'reservation_date' => $this->faker->dateTimeBetween('+1 day', '+2 weeks')->format('Y-m-d'),
            'reservation_time' => $this->faker->time('H:i'),
            'guest_count' => $this->faker->numberBetween(1, 10),
            'duration_minutes' => 120,
            'status' => 'pending',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function withFood(): static
    {
        return $this->state(fn () => ['reservation_type' => 'table_and_food']);
    }
}
