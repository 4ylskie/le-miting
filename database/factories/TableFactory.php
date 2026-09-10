<?php

namespace Database\Factories;

use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_number' => (string) $this->faker->unique()->numberBetween(1, 40),
            'capacity' => $this->faker->randomElement([2, 4, 6, 8, 10]),
            'status' => 'available',
        ];
    }
}
