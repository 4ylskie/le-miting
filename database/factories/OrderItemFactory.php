<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $menuItem = MenuItem::factory()->create();
        $quantity = $this->faker->numberBetween(1, 5);

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'unit_price' => $menuItem->price,
            'quantity' => $quantity,
        ];
    }
}
