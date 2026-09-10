<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'provider' => 'fiuu',
            'provider_reference' => $this->faker->unique()->uuid(),
            'status' => 'pending',
            'amount' => $this->faker->randomFloat(2, 20, 200),
            'currency' => 'MYR',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function forReservation(Reservation $reservation): static
    {
        return $this->state(fn () => [
            'reservation_id' => $reservation->id,
            'order_id' => null,
            'amount' => $reservation->reservationFoods->sum(fn ($item) => $item->unit_price * $item->quantity),
        ]);
    }
}
