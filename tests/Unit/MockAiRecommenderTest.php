<?php

use App\Services\Ai\MockAiRecommender;

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
