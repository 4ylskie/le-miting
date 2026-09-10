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

    expect($ids[0])->toBe(1);
});

it('ranks a category match above a single attribute match', function () {
    $recommender = new MockAiRecommender;

    $ids = $recommender->recommend([
        aiItem(1, 'main-course'),
        aiItem(2, 'appetizers', ['dietary_type' => 'Halal']),
    ], ['category' => 'main-course', 'dietary_type' => 'Halal']);

    expect($ids[0])->toBe(1);
});

it('scores matching attributes for all four preference fields', function () {
    $recommender = new MockAiRecommender;

    $ids = $recommender->recommend([
        aiItem(1, 'appetizers', ['spice_level' => 'Hot']),
        aiItem(2, 'appetizers'),
    ], ['spice_level' => 'Hot']);

    expect($ids[0])->toBe(1);
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
