<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Ai\AiRecommendationOptions;
use App\Services\Ai\AiRecommender;
use Database\Seeders\MenuCategorySeeder;
use Database\Seeders\MenuItemSeeder;
use Database\Seeders\MenuPreferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

test('the current menu exposes only meaningful canonical options and Rasa uses text only', function () {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    $expected = [
        'spiceLevels' => ['none', 'mild', 'medium', 'hot'],
        'proteinTypes' => ['chicken', 'beef', 'seafood', 'egg', 'none'],
        'dietaryTypes' => ['vegetarian'],
        'flavorProfiles' => ['savoury', 'sweet', 'creamy', 'tangy', 'bitter'],
    ];

    $rasa = $this->get(route('ai.recommendations'))->assertOk();
    $menu = $this->get(route('menu.index'))->assertOk();

    foreach ($expected as $key => $values) {
        expect($menu->viewData('filters')[$key]->all())->toBe($values);
        foreach ($values as $value) {
            $menu->assertSee(AiRecommendationOptions::label(array_keys(AiRecommendationOptions::VALUES)[array_search($key, array_keys($expected), true)], $value));
        }
    }
    foreach (array_keys(AiRecommendationOptions::VALUES) as $field) {
        $rasa->assertDontSee('name="'.$field.'"', false);
    }
    expect(MenuItem::where('is_available', true)->count())->toBe(27);
    foreach (MenuItem::withTrashed()->get() as $item) {
        foreach (AiRecommendationOptions::VALUES as $field => $values) {
            expect($item->$field === null || in_array($item->$field, $values, true))->toBeTrue();
        }
        if ($item->is_available) {
            expect($item->spice_level)->toBeString();
            expect($item->protein_type)->toBeString();
            expect($item->flavor_profile)->toBeString();
        }
    }
});

test('each supported text term filters mock Rasa by its verified dimension', function (string $field, string $query, string $value) {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    config(['ai.driver' => 'mock']);

    $menu = $this->get(route('menu.index', [$query => $value]))->assertOk()->viewData('items');
    $term = $value === 'none' ? ($field === 'spice_level' ? 'not spicy' : 'no primary protein') : $value;
    $recommendations = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $term])
        ->assertOk()->viewData('recommendations');

    expect($menu->total())->toBe(MenuItem::where('is_available', true)->where($field, $value)->count());
    expect($recommendations)->not->toBeEmpty();
    foreach ([...$menu->items(), ...$recommendations] as $item) {
        expect($item->$field)->toBe($value);
    }
})->with([
    ['spice_level', 'spice', 'none'], ['spice_level', 'spice', 'mild'],
    ['spice_level', 'spice', 'medium'], ['spice_level', 'spice', 'hot'],
    ['protein_type', 'protein', 'chicken'], ['protein_type', 'protein', 'beef'],
    ['protein_type', 'protein', 'seafood'], ['protein_type', 'protein', 'egg'],
    ['protein_type', 'protein', 'none'], ['dietary_type', 'dietary', 'vegetarian'],
    ['flavor_profile', 'flavor', 'savoury'], ['flavor_profile', 'flavor', 'sweet'],
    ['flavor_profile', 'flavor', 'creamy'], ['flavor_profile', 'flavor', 'tangy'],
    ['flavor_profile', 'flavor', 'bitter'],
]);

test('obsolete tags are rejected by customer and administrator write boundaries', function (string $field, string $query, string $obsolete) {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken', $field => $obsolete])
        ->assertSessionHasErrors($field);
    $this->get(route('menu.index', [$query => $obsolete]))->assertSessionHasErrors($query);
    $item = MenuItem::where('slug', 'rendang')->firstOrFail();
    $before = $item->getAttributes();
    $payload = $item->only(['name', 'slug', 'description', 'menu_category_id', 'price', 'is_available', 'is_featured']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->patch(route('admin.menu.items.update', $item), [...$payload, $field => $obsolete])
        ->assertSessionHasErrors($field);
    $this->post(route('admin.menu.items.store'), [...$payload, 'slug' => 'rejected-item', $field => $obsolete])
        ->assertSessionHasErrors($field);

    expect($item->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseMissing('menu_items', ['slug' => 'rejected-item']);
})->with([
    ['spice_level', 'spice', 'Extra Hot'],
    ['protein_type', 'protein', 'Fish'], ['protein_type', 'protein', 'Prawn'],
    ['protein_type', 'protein', 'Lamb'], ['dietary_type', 'dietary', 'Halal'],
    ['dietary_type', 'dietary', 'Vegan'], ['flavor_profile', 'flavor', 'Savory'],
    ['flavor_profile', 'flavor', 'Spicy'], ['flavor_profile', 'flavor', 'Sour'],
    ['flavor_profile', 'flavor', 'Salty'],
]);

test('unavailable inactive and deleted items cannot supply customer options', function () {
    MenuItem::factory()->unavailable()->create(['spice_level' => 'hot']);
    $inactive = MenuCategory::factory()->create(['is_active' => false]);
    MenuItem::factory()->for($inactive, 'category')->create(['spice_level' => 'hot']);
    $deleted = MenuItem::factory()->create(['spice_level' => 'hot']);
    $deleted->delete();
    MenuItem::factory()->create(['spice_level' => 'none']);

    $this->get(route('ai.recommendations'))->assertDontSee('value="hot"', false);
    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'hot', 'spice_level' => 'hot'])
        ->assertSessionHasErrors('spice_level');
});

test('Rasa and menu combine selected preferences with AND', function () {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    config(['ai.driver' => 'mock']);

    $rasa = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'not spicy beef'])
        ->assertOk()->viewData('recommendations');
    $menu = $this->get(route('menu.index', ['spice' => 'none', 'protein' => 'beef']))
        ->assertOk()->viewData('items');

    expect($rasa->pluck('slug')->all())->toBe(['sup-daging']);
    expect($menu->pluck('slug')->all())->toBe(['sup-daging']);
});

test('an impossible combination returns no matches without calling a provider', function () {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'vegetarian beef'])
        ->assertSee('No dishes match your preferences.')
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty());
});

test('Rasa passes canonical preferences and only matching records then rechecks returned IDs', function () {
    $preferences = ['spice_level' => 'none', 'protein_type' => 'none', 'dietary_type' => 'vegetarian', 'flavor_profile' => 'sweet'];
    $text = 'not spicy, no primary protein, vegetarian and sweet';
    $matching = MenuItem::factory()->create($preferences);
    $wrong = MenuItem::factory()->create([...$preferences, 'spice_level' => 'hot']);
    $this->mock(AiRecommender::class)->shouldReceive('recommend')->once()
        ->withArgs(fn (array $items, array $selected): bool => $selected === [...$preferences, 'preferences_text' => $text]
            && array_column($items, 'id') === [$matching->id]
            && $items[0]['protein_type'] === 'none')
        ->andReturn([$matching->id, $wrong->id]);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$matching->id]);
});

test('OpenAI recommendations also enforce the database preference boundary', function () {
    config(['ai.driver' => 'openai', 'openai.api_key' => 'test-only-not-a-real-key']);
    $matching = MenuItem::factory()->create(['protein_type' => 'beef']);
    $wrong = MenuItem::factory()->create(['protein_type' => 'chicken']);
    OpenAI::fake([CreateResponse::fake(['choices' => [['message' => [
        'content' => json_encode(['recommended_item_ids' => [$wrong->id, $matching->id]]),
    ]]]])]);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'beef'])
        ->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$matching->id]);
});

test('an administrator can persist canonical tags and clear optional dietary metadata', function () {
    $item = MenuItem::factory()->create();
    $payload = $item->only(['name', 'slug', 'description', 'menu_category_id', 'price', 'is_available', 'is_featured']);
    $tags = ['spice_level' => 'mild', 'protein_type' => 'egg', 'dietary_type' => null, 'flavor_profile' => 'savoury'];

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.menu.items.update', $item), [...$payload, ...$tags])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.menu.items.index'));

    $this->assertDatabaseHas('menu_items', ['id' => $item->id, ...$tags]);
});

test('an administrator can create a dish with the shared canonical vocabulary', function () {
    $category = MenuCategory::factory()->create();
    $tags = ['spice_level' => 'none', 'protein_type' => 'none', 'dietary_type' => 'vegetarian', 'flavor_profile' => 'sweet'];

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('admin.menu.items.store'), [
            'name' => 'Preference fixture dessert', 'slug' => 'preference-fixture-dessert',
            'description' => 'Dessert fixture.', 'menu_category_id' => $category->id,
            'price' => 10, 'is_available' => true, 'is_featured' => false, ...$tags,
        ])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.menu.items.index'));

    $this->assertDatabaseHas('menu_items', ['slug' => 'preference-fixture-dessert', ...$tags]);
});

test('metadata reseeding preserves all other attributes and reconciles retired tags too', function () {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    $legacy = MenuItem::factory()->unavailable()->create([
        'spice_level' => 'Extra Hot', 'protein_type' => 'Prawn',
        'dietary_type' => 'Halal', 'flavor_profile' => 'Savory',
    ]);
    $legacy->delete();
    $before = MenuItem::withTrashed()->get()->keyBy('id')
        ->map(fn ($item) => collect($item->getAttributes())->except([
            'spice_level', 'protein_type', 'dietary_type', 'flavor_profile', 'updated_at',
        ])->all())->all();

    $this->seed(MenuPreferenceSeeder::class);
    $this->seed(MenuPreferenceSeeder::class);

    $after = MenuItem::withTrashed()->get()->keyBy('id')
        ->map(fn ($item) => collect($item->getAttributes())->except([
            'spice_level', 'protein_type', 'dietary_type', 'flavor_profile', 'updated_at',
        ])->all())->all();
    expect($after)->toBe($before);
    $this->assertDatabaseHas('menu_items', [
        'id' => $legacy->id, 'spice_level' => 'hot', 'protein_type' => 'seafood',
        'dietary_type' => null, 'flavor_profile' => 'savoury', 'is_available' => false,
    ]);
    $this->assertDatabaseHas('menu_items', ['slug' => 'nasi-lemak', 'protein_type' => 'seafood', 'dietary_type' => null]);
    $this->assertDatabaseHas('menu_items', ['slug' => 'kopi-o', 'spice_level' => 'none', 'flavor_profile' => 'bitter']);
    $this->assertDatabaseHas('menu_items', ['slug' => 'roti-canai', 'dietary_type' => null]);
});
