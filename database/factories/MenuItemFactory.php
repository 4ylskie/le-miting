<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'menu_category_id' => MenuCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 8, 60),
            'spice_level' => $this->faker->optional()->randomElement(['Mild', 'Medium', 'Hot', 'Extra Hot']),
            'protein_type' => $this->faker->optional()->randomElement(['Chicken', 'Beef', 'Lamb', 'Fish', 'Prawn', 'None']),
            'dietary_type' => $this->faker->optional()->randomElement(['Halal', 'Vegetarian', 'Vegan']),
            'flavor_profile' => $this->faker->optional()->randomElement(['Spicy', 'Sweet', 'Savory', 'Sour', 'Salty']),
            'is_available' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
