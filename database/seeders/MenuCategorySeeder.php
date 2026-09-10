<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuCategorySeeder extends Seeder
{
    /**
     * Seed the menu categories.
     *
     * @return array<string, int>
     */
    public function run(): array
    {
        $categories = ['Appetizers', 'Main Course', 'Grills', 'Soups', 'Desserts', 'Beverages'];

        $ids = [];
        foreach ($categories as $index => $category) {
            $model = MenuCategory::updateOrCreate(
                ['slug' => Str::slug($category)],
                [
                    'name' => $category,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
            $ids[$model->slug] = $model->id;
        }

        return $ids;
    }
}
