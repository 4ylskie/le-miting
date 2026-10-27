<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Table;
use App\Models\User;
use App\Services\Ai\AiRecommender;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\ReservationService;
use Database\Seeders\MenuCategorySeeder;
use Database\Seeders\MenuItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function menuConsistencyXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

function menuConsistencyPreferenceText(MenuItem $item): string
{
    return $item->spice_level === 'none' ? 'not spicy' : (string) $item->spice_level;
}

test('non-applicable metadata is hidden on every customer dish surface', function (string $surface, ?string $dietary) {
    $item = MenuItem::factory()->featured()->create([
        'name' => 'Coffee metadata fixture', 'spice_level' => 'none',
        'protein_type' => 'none', 'dietary_type' => $dietary, 'flavor_profile' => 'bitter',
    ]);
    config(['ai.driver' => 'mock']);
    if ($surface === 'rasa') {
        $recommender = Mockery::mock(AiRecommender::class);
        $recommender->shouldReceive('recommend')->once()->andReturn([$item->id]);
        $this->app->instance(AiRecommender::class, $recommender);
    }
    $response = match ($surface) {
        'menu' => $this->get(route('menu.index')),
        'detail' => $this->get(route('menu.show', $item)),
        'home' => $this->get(route('home')),
        'rasa' => $this->post(route('ai.recommendations.recommend'), [
            'preferences_text' => menuConsistencyPreferenceText($item),
        ]),
    };

    $response->assertOk();
    $attributes = menuConsistencyXPath($response->getContent())->query('//*[@aria-label="Dish attributes"]');
    expect($attributes->length)->toBe(1);
    $text = $attributes->item(0)->textContent;
    expect($text)->toContain('Flavour', 'Bitter')
        ->not->toContain('Spice', 'Protein', 'Dietary', 'None', 'Not Spicy');
})->with(['menu', 'detail', 'home', 'rasa'])->with([null, 'none']);

test('meaningful dish attributes keep readable labels across customer surfaces', function (string $surface) {
    $item = MenuItem::factory()->featured()->create([
        'spice_level' => 'hot', 'protein_type' => 'beef', 'dietary_type' => null, 'flavor_profile' => 'savoury',
    ]);
    config(['ai.driver' => 'mock']);
    if ($surface === 'rasa') {
        $recommender = Mockery::mock(AiRecommender::class);
        $recommender->shouldReceive('recommend')->once()->andReturn([$item->id]);
        $this->app->instance(AiRecommender::class, $recommender);
    }
    $response = match ($surface) {
        'menu' => $this->get(route('menu.index')),
        'detail' => $this->get(route('menu.show', $item)),
        'home' => $this->get(route('home')),
        'rasa' => $this->post(route('ai.recommendations.recommend'), [
            'preferences_text' => menuConsistencyPreferenceText($item),
        ]),
    };

    $text = menuConsistencyXPath($response->assertOk()->getContent())
        ->query('//*[@aria-label="Dish attributes"]')->item(0)->textContent;
    expect($text)->toContain('Spice', 'Hot', 'Protein', 'Beef', 'Flavour', 'Savoury')->not->toContain('Dietary');
})->with(['menu', 'detail', 'home', 'rasa']);

test('an item without meaningful metadata has no empty attribute container', function () {
    $item = MenuItem::factory()->make([
        'spice_level' => 'none', 'protein_type' => 'none', 'dietary_type' => null, 'flavor_profile' => null,
    ]);

    $this->blade('<x-menu-metadata :item="$item" />', ['item' => $item])
        ->assertDontSee('Dish attributes');
});

test('all seeded beverages render flavour without irrelevant labels', function (string $slug, string $flavor) {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    $item = MenuItem::where('slug', $slug)->firstOrFail();

    $response = $this->get(route('menu.show', $item))->assertOk();

    $text = menuConsistencyXPath($response->getContent())
        ->query('//*[@aria-label="Dish attributes"]')->item(0)->textContent;
    expect($text)->toContain('Flavour', $flavor)->not->toContain('Spice', 'Protein', 'Dietary');
    $this->assertDatabaseHas('menu_items', [
        'id' => $item->id, 'spice_level' => 'none', 'protein_type' => 'none', 'dietary_type' => null,
    ]);
})->with([
    ['kopi-o', 'Bitter'], ['teh-o', 'Bitter'], ['teh-tarik', 'Sweet'],
    ['bandung', 'Sweet'], ['sirap-limau', 'Tangy'],
]);

test('admin forms retain canonical spice values while Rasa remains text-only', function (bool $edit) {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    $item = MenuItem::where('slug', 'rendang')->firstOrFail();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $route = $edit ? route('admin.menu.items.edit', $item) : route('admin.menu.items.create');

    $admin = $this->get($route)->assertOk();
    $rasa = $this->get(route('ai.recommendations'))->assertOk();

    $adminOptions = menuConsistencyXPath($admin->getContent())->query('//select[@name="spice_level"]/option');
    expect(array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($adminOptions)))
        ->toBe(['none', 'mild', 'medium', 'hot']);
    $rasaXpath = menuConsistencyXPath($rasa->getContent());
    expect($rasaXpath->query('//textarea[@name="preferences_text"]')->length)->toBe(1)
        ->and($rasaXpath->query('//select[@name="spice_level" or @name="protein_type" or @name="dietary_type" or @name="flavor_profile"]')->length)->toBe(0);
})->with([false, true]);

test('obsolete spice spellings are rejected at every input boundary', function (string $obsolete) {
    $item = MenuItem::factory()->create(['spice_level' => 'mild']);
    $payload = $item->only(['name', 'slug', 'description', 'menu_category_id', 'price', 'is_available', 'is_featured']);
    $this->post(route('ai.recommendations.recommend'), ['spice_level' => $obsolete])
        ->assertSessionHasErrors('spice_level');
    $this->get(route('menu.index', ['spice' => $obsolete]))->assertSessionHasErrors('spice');
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->patch(route('admin.menu.items.update', $item), [...$payload, 'spice_level' => $obsolete])
        ->assertSessionHasErrors('spice_level');
    $this->post(route('admin.menu.items.store'), [...$payload, 'slug' => 'rejected-spice', 'spice_level' => $obsolete])
        ->assertSessionHasErrors('spice_level');

    $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'spice_level' => 'mild']);
    $this->assertDatabaseMissing('menu_items', ['slug' => 'rejected-spice']);
})->with(['Not Spicy', 'not_spicy', 'None', 'Mild', 'Medium', 'Hot', 'extra hot']);

test('customer and admin surfaces follow the established catalog eligibility rules', function (string $state) {
    $category = MenuCategory::factory()->create(['is_active' => $state !== 'inactive category']);
    $item = MenuItem::factory()->featured()->for($category, 'category')->create([
        'name' => 'Catalog eligibility fixture', 'is_available' => $state !== 'unavailable',
    ]);
    if ($state === 'deleted') {
        $item->delete();
    }
    $eligible = $state === 'available';
    $expected = $eligible ? [$item->id] : [];
    config(['ai.driver' => 'mock']);
    if ($eligible) {
        $recommender = Mockery::mock(AiRecommender::class);
        $recommender->shouldReceive('recommend')->once()->andReturn([$item->id]);
        $this->app->instance(AiRecommender::class, $recommender);
    }

    $menu = $this->get(route('menu.index'))->assertOk()->viewData('items');
    expect($menu->pluck('id')->all())->toBe($expected);
    $this->get(route('menu.show', $item))->assertStatus($eligible ? 200 : 404);
    $home = $this->get(route('home'))->assertOk()->viewData('featuredItems');
    expect($home->pluck('id')->all())->toBe($expected);
    $rasa = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => menuConsistencyPreferenceText($item),
    ])
        ->assertOk()->viewData('recommendations');
    expect($rasa->pluck('id')->all())->toBe($expected);
    $reservation = $this->get(route('reservation.table-food'))->assertOk()->viewData('menuItems');
    expect($reservation->pluck('id')->all())->toBe($expected);
    $cart = $this->post(route('cart.add'), ['menu_item_id' => $item->id, 'quantity' => 1]);
    if ($eligible) {
        $cart->assertSessionHasNoErrors()->assertSessionHas('cart.order.items.'.$item->id.'.quantity', 1);
    } else {
        $cart->assertSessionHasErrors('menu_item_id')->assertSessionMissing('cart.order.items.'.$item->id);
    }
    $admin = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.menu.items.index'))->assertOk()->viewData('items');
    expect($admin->pluck('id')->all())->toBe($state === 'deleted' ? [] : [$item->id]);
})->with(['available', 'unavailable', 'deleted', 'inactive category']);

test('ineligible dishes cannot enter reservation food orders', function (string $state) {
    $category = MenuCategory::factory()->create(['is_active' => $state !== 'inactive category']);
    $item = MenuItem::factory()->for($category, 'category')->create(['is_available' => $state !== 'unavailable']);
    if ($state === 'deleted') {
        $item->delete();
    }
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);

    $this->post(route('reservation.store'), [
        'reservation_type' => 'table_and_food', 'table_id' => $table->id,
        'reservation_date' => now()->addDay()->toDateString(), 'reservation_time' => '11:00',
        'guest_count' => 2, 'customer_name' => 'Guest', 'customer_email' => 'guest@example.com',
        'customer_phone' => '0123456789',
        'food_items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('food_items.0.menu_item_id');

    $this->assertDatabaseCount('reservations', 0);
    $this->assertDatabaseCount('payments', 0);
})->with(['unavailable', 'deleted', 'inactive category']);

test('a category deactivated after cart selection is rejected by final order and reservation checks', function () {
    $category = MenuCategory::factory()->create();
    $item = MenuItem::factory()->for($category, 'category')->create(['price' => 12]);
    $carts = app(CartService::class);
    $carts->addOrderItem($item, 1);
    $category->update(['is_active' => false]);
    $guest = ['customer_name' => 'Guest', 'customer_email' => 'guest@example.com', 'customer_phone' => '0123456789'];
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);

    $summary = $carts->orderSummary();
    expect($summary['has_unavailable_items'])->toBeTrue();
    expect($summary['total'])->toBe(0.0);
    expect(fn () => app(OrderService::class)->createFromCart([...$guest, 'order_type' => 'dine-in']))
        ->toThrow(ValidationException::class);
    expect(fn () => app(ReservationService::class)->createTableAndFood([
        ...$guest, 'table_id' => $table->id, 'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '11:00', 'guest_count' => 2,
        'food_items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
    ]))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('reservations', 0);
});
