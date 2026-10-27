<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
            ['grills', 'Satay Skewers', 'Grilled chicken satay with peanut sauce', 12.00, false],
            ['appetizers', 'Ulam-ulaman', 'Fresh vegetables served with sambal belacan', 10.00, false],
            ['appetizers', 'Begedil Kentang', 'Potato patties with herbs and fried shallots', 10.00, false],
            ['appetizers', 'Roti Canai', 'Flaky Malaysian flatbread with curry dip', 6.00, true],
            ['appetizers', 'Roti Telur', 'Flaky Malaysian flatbread filled with egg and served with curry dip', 7.50, true],
            ['main-course', 'Nasi Lemak', 'Fragrant coconut rice with sambal, anchovies and egg', 15.00, true],
            ['main-course', 'Rendang', 'Tender beef rendang in rich coconut spices', 22.00, true],
            ['main-course', 'Ayam Masak Lemak Cili Api', 'Chicken cooked in coconut milk with turmeric and bird’s eye chillies', 18.00, false],
            ['main-course', 'Ayam Goreng Berempah', 'Chicken marinated with spices and fried', 16.00, false],
            ['main-course', 'Ikan Kembung Goreng', 'Mackerel seasoned with turmeric and fried', 15.00, false],
            ['main-course', 'Sambal Udang Petai', 'Prawns and petai cooked in sambal', 20.00, false],
            ['main-course', 'Kangkung Belacan', 'Water spinach stir-fried with shrimp paste', 12.00, false],
            ['main-course', 'Sayur Lodeh', 'Mixed vegetables simmered in coconut milk', 12.00, false],
            ['main-course', 'Sambal Telur', 'Boiled eggs served in sambal', 10.00, false],
            ['main-course', 'Nasi Goreng', 'Malaysian fried rice with shrimp and egg', 14.50, false],
            ['grills', 'Ikan Bakar', 'Grilled fish served with sambal', 20.00, false],
            ['grills', 'Ayam Percik', 'Grilled chicken with a spiced coconut sauce', 18.00, false],
            ['soups', 'Sup Ayam', 'Chicken soup with vegetables and spices', 14.00, false],
            ['soups', 'Sup Daging', 'Beef soup with vegetables and spices', 18.00, false],
            ['desserts', 'Cendol', 'Shaved ice dessert with coconut milk and gula melaka', 8.00, true],
            ['desserts', 'Kuih Lapis', 'Layered steamed rice cake dessert', 6.50, false],
            ['desserts', 'Bubur Kacang Hijau', 'Mung beans simmered with coconut milk and palm sugar', 10.00, false],
            ['beverages', 'Teh Tarik', 'Frothy pulled milk tea', 5.00, false],
            ['beverages', 'Bandung', 'Rose syrup milk drink', 5.50, false],
            ['beverages', 'Kopi O', 'Strong black coffee', 4.00, false],
            ['beverages', 'Sirap Limau', 'Rose syrup drink with lime', 10.00, false],
            ['beverages', 'Teh O', 'Black tea without milk', 10.00, false],
        ];

        DB::transaction(function () use ($items, $categories): void {
            $protected = ['nasi-lemak', 'rendang', 'roti-canai', 'roti-telur', 'cendol'];

            foreach ($items as [$categorySlug, $name, $description, $price, $featured]) {
                $slug = Str::slug($name);
                $attributes = [
                    'menu_category_id' => $categories[$categorySlug],
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'is_available' => true,
                    'is_featured' => $featured,
                    'sort_order' => $featured ? array_search($slug, $protected, true) + 1 : 0,
                ];

                if (in_array($slug, $protected, true)) {
                    MenuItem::withTrashed()->firstOrCreate(['slug' => $slug], $attributes);
                } else {
                    MenuItem::updateOrCreate(['slug' => $slug], $attributes);
                }
            }

            MenuItem::whereIn('slug', [
                'spring-rolls', 'butter-chicken', 'grilled-lamb-chops', 'beef-short-ribs',
                'grilled-salmon', 'tom-yum', 'sup-buntut', 'chocolate-lava-cake',
            ])->where('is_available', true)->update(['is_available' => false]);
        });

        $this->call(MenuPreferenceSeeder::class);
        $this->call(MenuImageSeeder::class);
    }
}
