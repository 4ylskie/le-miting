<?php

use App\Services\Ai\AiRecommendationOptions;
use App\Services\Ai\MockAiRecommender;

it('recognizes culinary cues across all 35 audited catalog dishes', function (string $name, string $description, string $flavor, array $traits) {
    $item = aiItem(1, overrides: ['name' => $name, 'description' => $description, 'flavor_profile' => $flavor]);

    expect((new AiRecommendationOptions)->culinaryTraits($item))->toContain(...$traits);
})->with([
    ['Satay Skewers', 'Grilled chicken satay with peanut sauce', 'savoury', ['grilled', 'savoury']],
    ['Spring Rolls', 'Crispy vegetable spring rolls', 'savoury', ['crispy', 'vegetables']],
    ['Roti Canai', 'Flaky Malaysian flatbread with curry dip', 'savoury', ['bread', 'savoury']],
    ['Nasi Lemak', 'Fragrant coconut rice with sambal, anchovies and egg', 'savoury', ['rice', 'creamy', 'fish']],
    ['Rendang', 'Tender beef rendang in rich coconut spices', 'savoury', ['creamy', 'spiced', 'slow_cooked']],
    ['Butter Chicken', 'Creamy tomato butter chicken with naan', 'creamy', ['creamy', 'bread']],
    ['Nasi Goreng', 'Malaysian fried rice with shrimp and egg', 'savoury', ['rice', 'fried', 'prawns']],
    ['Grilled Lamb Chops', 'Char-grilled lamb chops with mint sauce', 'savoury', ['grilled', 'lamb']],
    ['Beef Short Ribs', 'Slow-cooked BBQ beef short ribs', 'sweet', ['grilled', 'slow_cooked']],
    ['Grilled Salmon', 'Atlantic salmon with lemon herb butter', 'savoury', ['fish', 'grilled', 'creamy', 'tangy']],
    ['Tom Yum', 'Hot and sour Thai soup with prawns', 'tangy', ['tangy', 'prawns']],
    ['Sup Buntut', 'Indonesian oxtail soup with vegetables', 'savoury', ['savoury', 'vegetables']],
    ['Cendol', 'Shaved ice dessert with coconut milk and gula melaka', 'sweet', ['creamy', 'sweet']],
    ['Kuih Lapis', 'Layered steamed rice cake dessert', 'sweet', ['steamed', 'sweet']],
    ['Chocolate Lava Cake', 'Warm chocolate cake with molten center', 'sweet', ['chocolate', 'sweet']],
    ['Teh Tarik', 'Frothy pulled milk tea', 'sweet', ['tea', 'creamy']],
    ['Bandung', 'Rose syrup milk drink', 'sweet', ['sweet', 'creamy']],
    ['Kopi O', 'Strong black coffee', 'bitter', ['coffee', 'bitter']],
    ['Roti Telur', 'Flaky Malaysian flatbread filled with egg and served with curry dip', 'savoury', ['bread', 'savoury']],
    ['Ulam-ulaman', 'Fresh vegetables served with sambal belacan', 'savoury', ['vegetables', 'spiced']],
    ['Begedil Kentang', 'Potato patties with herbs and fried shallots', 'savoury', ['fried', 'vegetables']],
    ['Ayam Masak Lemak Cili Api', 'Chicken cooked in coconut milk with turmeric and bird’s eye chillies', 'creamy', ['creamy', 'spiced']],
    ['Ayam Goreng Berempah', 'Chicken marinated with spices and fried', 'savoury', ['fried', 'spiced']],
    ['Ikan Kembung Goreng', 'Mackerel seasoned with turmeric and fried', 'savoury', ['fish', 'fried']],
    ['Sambal Udang Petai', 'Prawns and petai cooked in sambal', 'savoury', ['prawns', 'spiced']],
    ['Kangkung Belacan', 'Water spinach stir-fried with shrimp paste', 'savoury', ['vegetables', 'fried']],
    ['Sayur Lodeh', 'Mixed vegetables simmered in coconut milk', 'creamy', ['vegetables', 'creamy', 'slow_cooked']],
    ['Sambal Telur', 'Boiled eggs served in sambal', 'savoury', ['savoury', 'spiced']],
    ['Ikan Bakar', 'Grilled fish served with sambal', 'savoury', ['fish', 'grilled', 'spiced']],
    ['Ayam Percik', 'Grilled chicken with a spiced coconut sauce', 'creamy', ['creamy', 'grilled', 'savoury', 'spiced']],
    ['Sup Ayam', 'Chicken soup with vegetables and spices', 'savoury', ['savoury', 'vegetables']],
    ['Sup Daging', 'Beef soup with vegetables and spices', 'savoury', ['savoury', 'vegetables']],
    ['Bubur Kacang Hijau', 'Mung beans simmered with coconut milk and palm sugar', 'sweet', ['vegetables', 'creamy', 'sweet']],
    ['Sirap Limau', 'Rose syrup drink with lime', 'tangy', ['sweet', 'tangy']],
    ['Teh O', 'Black tea without milk', 'bitter', ['tea', 'bitter']],
]);

it('does not turn negated dairy words into a creamy taste match', function () {
    $traits = (new AiRecommendationOptions)->culinaryTraits([
        'name' => 'Teh O', 'description' => 'Black tea without milk', 'flavor_profile' => 'bitter',
    ]);

    expect($traits)->not->toContain('creamy');
});

it('relaxes only the soft intersection while preserving both price bounds and protein', function () {
    $items = [
        aiItem(1, 'grills', ['price' => 18, 'protein_type' => 'chicken', 'flavor_profile' => 'creamy', 'spice_level' => 'hot']),
        aiItem(2, 'grills', ['price' => 15, 'protein_type' => 'chicken', 'flavor_profile' => 'savoury', 'spice_level' => 'mild']),
        aiItem(3, 'grills', ['price' => 25, 'protein_type' => 'chicken']),
        aiItem(4, 'grills', ['price' => 18, 'protein_type' => 'seafood']),
        aiItem(5, 'grills', ['price' => 9, 'protein_type' => 'chicken']),
        aiItem(6, 'appetizers', ['price' => 15, 'protein_type' => 'chicken']),
        aiItem(7, 'grills', ['price' => 15, 'protein_type' => 'chicken', 'is_available' => false]),
    ];

    expect((new MockAiRecommender)->recommend($items, [
        'category' => 'grills', 'protein_type' => 'chicken', 'min_price' => 10, 'max_price' => 20,
        'spice_level' => 'mild', 'flavor_profile' => 'creamy',
    ]))->toBe([1, 2]);
});

it('uses coconut descriptions as a flavour match when no stored creamy tag matches', function () {
    $items = [
        aiItem(1, 'desserts', ['name' => 'Cendol', 'description' => 'Coconut milk and palm sugar', 'flavor_profile' => 'sweet']),
        aiItem(2, 'beverages', ['name' => 'Teh O', 'description' => 'Black tea without milk', 'flavor_profile' => 'bitter']),
    ];

    expect((new MockAiRecommender)->recommend($items, ['flavor_profile' => 'creamy']))->toBe([1]);
});

it('never relaxes vegetarian or negative spice restrictions for a soft fallback', function (array $preferences) {
    expect((new MockAiRecommender)->recommend([
        aiItem(1, overrides: ['spice_level' => 'hot', 'protein_type' => 'chicken', 'dietary_type' => null]),
    ], $preferences))->toBe([]);
})->with([
    [['dietary_type' => 'vegetarian', 'flavor_profile' => 'creamy']],
    [['spice_level' => 'none', 'flavor_profile' => 'creamy']],
    [['spice_levels' => ['none', 'mild'], 'flavor_profile' => 'creamy']],
    [['excluded_protein_types' => ['chicken'], 'flavor_profile' => 'creamy']],
]);

function aiItem(int $id, string $category = 'appetizers', array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'name' => 'Item '.$id,
        'description' => 'A dish',
        'category' => $category,
        'price' => 12.0,
        'spice_level' => null,
        'protein_type' => null,
        'dietary_type' => null,
        'flavor_profile' => null,
    ], $overrides);
}

it('matches the category preference using slugs', function () {
    $recommender = new MockAiRecommender;

    $ids = $recommender->recommend([
        aiItem(1, 'main-course'),
        aiItem(2, 'appetizers'),
    ], ['category' => 'main-course']);

    expect($ids)->toBe([1]);
});

it('requires category and attribute matches together', function () {
    $recommender = new MockAiRecommender;

    $ids = $recommender->recommend([
        aiItem(1, 'main-course'),
        aiItem(2, 'appetizers', ['dietary_type' => 'vegetarian']),
    ], ['category' => 'main-course', 'dietary_type' => 'vegetarian']);

    expect($ids)->toBe([]);
});

it('excludes unmatched fallback dishes', function () {
    $recommender = new MockAiRecommender;

    $ids = $recommender->recommend([
        aiItem(1, 'appetizers', ['spice_level' => 'hot']),
        aiItem(2, 'appetizers'),
    ], ['spice_level' => 'hot']);

    expect($ids)->toBe([1]);
});

it('ranks Malay chili signals ahead of identifier order for spicy requests', function () {
    $recommender = new MockAiRecommender;

    $ids = $recommender->recommend([
        aiItem(1, 'main-course', ['name' => 'Satay Skewers', 'description' => 'Grilled chicken satay', 'spice_level' => 'medium']),
        aiItem(2, 'main-course', ['name' => 'Ayam Masak Lemak Cili Api', 'description' => 'Chicken cooked with bird’s eye chillies', 'spice_level' => 'hot']),
        aiItem(3, 'main-course', ['name' => 'Rendang', 'description' => 'Beef cooked in coconut spices', 'spice_level' => 'hot']),
        aiItem(4, 'main-course', ['name' => 'Mild Chili Dish', 'description' => 'Cili sauce', 'spice_level' => 'mild']),
    ], ['spice_levels' => ['medium', 'hot'], 'preferences_text' => 'something spicy']);

    expect($ids)->toBe([2, 3, 1]);
});

it('ranks an explicitly named menu item ahead of other eligible dishes', function () {
    $recommender = new MockAiRecommender;

    $ids = $recommender->recommend([
        aiItem(1, 'main-course', ['name' => 'Chicken Curry', 'protein_type' => 'chicken', 'spice_level' => 'hot']),
        aiItem(2, 'main-course', ['name' => 'Ayam Masak Lemak Cili Api', 'protein_type' => 'chicken', 'spice_level' => 'hot']),
    ], [
        'protein_type' => 'chicken', 'spice_level' => 'hot',
        'preferences_text' => 'Ayam Masak Lemak Cili Api',
    ]);

    expect($ids)->toBe([2, 1]);
});

it('limits the number of recommendations to the configured maximum', function () {
    $recommender = new MockAiRecommender;

    $items = collect(range(1, 12))
        ->map(fn (int $id) => aiItem($id, 'beverages'))
        ->all();

    $ids = $recommender->recommend($items, []);

    expect($ids)->toHaveCount((int) config('ai.max_recommendations'));
});

it('returns an empty list when there are no items', function () {
    $recommender = new MockAiRecommender;

    expect($recommender->recommend([], []))->toBe([]);
});
