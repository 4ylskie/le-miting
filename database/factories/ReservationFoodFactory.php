<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\ReservationFood;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationFood>
 */
class ReservationFoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $menuItem = MenuItem::factory()->create();

        return [
            'reservation_id' => Reservation::factory(),
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'unit_price' => $menuItem->price,
            'quantity' => $this->faker->numberBetween(1, 5),
        ];
    }
}
