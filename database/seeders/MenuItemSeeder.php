<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuItemSeeder extends Seeder
{
    /**
     * Seed the menu items.
     */
    public function run(): void
    {
        $categories = MenuCategory::pluck('id', 'slug')->all();

        $items = [
            ['appetizers', 'Satay Skewers', 'Grilled chicken satay with peanut sauce', 12.00, 'Medium', 'Chicken', 'Halal', 'Savory', false],
            ['appetizers', 'Spring Rolls', 'Crispy vegetable spring rolls', 8.50, 'Mild', 'None', 'Vegetarian', 'Savory', false],
            ['appetizers', 'Roti Canai', 'Flaky Malaysian flatbread with curry dip', 6.00, 'Mild', 'None', 'Vegetarian', 'Savory', false],
            ['main-course', 'Nasi Lemak', 'Fragrant coconut rice with sambal, anchovies and egg', 15.00, 'Medium', 'None', 'Halal', 'Spicy', true],
            ['main-course', 'Rendang', 'Tender beef rendang in rich coconut spices', 22.00, 'Hot', 'Beef', 'Halal', 'Spicy', true],
            ['main-course', 'Butter Chicken', 'Creamy tomato butter chicken with naan', 20.00, 'Mild', 'Chicken', 'Halal', 'Savory', true],
            ['main-course', 'Nasi Goreng', 'Malaysian fried rice with shrimp and egg', 14.50, 'Medium', 'Prawn', 'Halal', 'Savory', false],
            ['grills', 'Grilled Lamb Chops', 'Char-grilled lamb chops with mint sauce', 45.00, 'Medium', 'Lamb', 'Halal', 'Savory', true],
            ['grills', 'Beef Short Ribs', 'Slow-cooked BBQ beef short ribs', 48.00, 'Mild', 'Beef', 'Halal', 'Sweet', false],
            ['grills', 'Grilled Salmon', 'Atlantic salmon with lemon herb butter', 38.00, 'Mild', 'Fish', 'Halal', 'Savory', true],
            ['soups', 'Tom Yum', 'Hot and sour Thai soup with prawns', 16.00, 'Hot', 'Prawn', 'Halal', 'Sour', false],
            ['soups', 'Sup Buntut', 'Indonesian oxtail soup with vegetables', 18.00, 'Mild', 'Beef', 'Halal', 'Savory', false],
            ['desserts', 'Cendol', 'Shaved ice dessert with coconut milk and gula melaka', 8.00, 'Mild', 'None', 'Vegetarian', 'Sweet', true],
            ['desserts', 'Kuih Lapis', 'Layered steamed rice cake dessert', 6.50, 'Mild', 'None', 'Vegetarian', 'Sweet', false],
            ['desserts', 'Chocolate Lava Cake', 'Warm chocolate cake with molten center', 12.00, 'Mild', 'None', 'Vegetarian', 'Sweet', false],
            ['beverages', 'Teh Tarik', 'Frothy pulled milk tea', 5.00, 'Mild', 'None', 'Halal', 'Sweet', false],
            ['beverages', 'Bandung', 'Rose syrup milk drink', 5.50, 'Mild', 'None', 'Halal', 'Sweet', false],
            ['beverages', 'Kopi O', 'Strong black coffee', 4.00, 'Mild', 'None', 'Halal', 'Salty', false],
        ];

        foreach ($items as [$categorySlug, $name, $description, $price, $spice, $protein, $dietary, $flavor, $featured]) {
            MenuItem::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'menu_category_id' => $categories[$categorySlug],
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'spice_level' => $spice,
                    'protein_type' => $protein,
                    'dietary_type' => $dietary,
                    'flavor_profile' => $flavor,
                    'is_available' => true,
                    'is_featured' => $featured,
                    'sort_order' => 0,
                ]
            );
        }
    }
}
