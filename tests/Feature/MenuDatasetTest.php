<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use Database\Seeders\MenuCategorySeeder;
use Database\Seeders\MenuItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the kampung menu has 27 dishes across three pages with working detail links', function () {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    $expected = [
        'Satay Skewers', 'Ulam-ulaman', 'Begedil Kentang', 'Roti Canai', 'Roti Telur',
        'Nasi Lemak', 'Rendang', 'Ayam Masak Lemak Cili Api', 'Ayam Goreng Berempah',
        'Ikan Kembung Goreng', 'Sambal Udang Petai', 'Kangkung Belacan', 'Sayur Lodeh',
        'Sambal Telur', 'Nasi Goreng', 'Ikan Bakar', 'Ayam Percik', 'Sup Ayam', 'Sup Daging',
        'Cendol', 'Kuih Lapis', 'Bubur Kacang Hijau', 'Teh Tarik', 'Bandung', 'Kopi O', 'Sirap Limau', 'Teh O',
    ];

    $seen = [];
    foreach ([1, 2, 3] as $page) {
        $response = $this->get(route('menu.index', ['page' => $page]))->assertOk();
        $response->assertSee('of 27 dishes.')->assertSee('Page '.$page.' of 3.');
        $items = $response->viewData('items');
        expect($items->total())->toBe(27);
        expect($items->lastPage())->toBe(3);
        expect($items->items())->toHaveCount(9);
        foreach ($items as $item) {
            $seen[] = $item->name;
            $response->assertSee($item->name);
            $this->get(route('menu.show', $item))->assertOk()->assertSee($item->description);
            expect($item->category->is_active)->toBeTruthy();
        }
    }
    expect($seen)->toHaveCount(27)->toEqualCanonicalizing($expected);
    expect(MenuItem::where('is_featured', true)->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Nasi Lemak', 'Rendang', 'Roti Canai', 'Roti Telur', 'Cendol']);

    $newItems = MenuItem::whereNotIn('slug', [
        'satay-skewers', 'roti-canai', 'roti-telur', 'nasi-lemak', 'rendang', 'nasi-goreng',
        'cendol', 'kuih-lapis', 'teh-tarik', 'bandung', 'kopi-o',
    ])->get();
    expect($newItems)->toHaveCount(16);
    foreach ($newItems as $item) {
        expect((float) $item->price)->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(20);
        expect($item->image_path)->toBe('menu/'.str_replace('-', '', $item->slug).'.jpg');
        expect($item->spice_level)->not->toBeNull();
    }
});

test('a sixteen dish filter can be cleared to restore the complete three page menu', function (array $filter) {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);

    $filtered = $this->get(route('menu.index', $filter))->assertOk()
        ->assertSee('of 16 matching dishes.')->assertSee('View all 27 dishes');
    expect($filtered->viewData('items')->total())->toBe(16);

    $document = new DOMDocument;
    $document->loadHTML($filtered->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $reset = (new DOMXPath($document))->query('//a[normalize-space(.)="View all 27 dishes"]')->item(0);
    expect($reset->getAttribute('href'))->toBe(route('menu.index'));
    $fullMenu = $this->get($reset->getAttribute('href'))->assertOk()
        ->assertSee('Showing 1–9 of 27 dishes.')->assertSee('Page 1 of 3.');
    expect($fullMenu->viewData('items')->lastPage())->toBe(3);
})->with([
    'not spicy' => [['spice' => 'none']],
    'savoury' => [['flavor' => 'savoury']],
]);

test('reseeding preserves non-metadata attributes of existing protected dishes', function (string $slug) {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    $item = MenuItem::where('slug', $slug)->firstOrFail();
    $item->update([
        'description' => 'Existing restaurant description.', 'price' => 19.25,
        'image_path' => 'menu/existing-photo.jpg', 'sort_order' => 71,
        'spice_level' => 'medium', 'dietary_type' => null,
        'is_available' => false, 'is_featured' => true,
    ]);
    $metadata = ['spice_level', 'protein_type', 'dietary_type', 'flavor_profile', 'updated_at'];
    $before = collect($item->fresh()->getAttributes())->except($metadata)->all();

    $this->seed(MenuItemSeeder::class);

    expect(collect($item->fresh()->getAttributes())->except($metadata)->all())->toBe($before);
    expect(MenuItem::where('slug', $slug)->count())->toBe(1);
})->with(['nasi-lemak', 'rendang', 'roti-canai', 'roti-telur', 'cendol']);

test('retired dishes stay linked to unchanged transaction snapshots after repeat seeding', function () {
    $this->seed(MenuCategorySeeder::class);
    $retiredSlugs = [
        'spring-rolls', 'butter-chicken', 'grilled-lamb-chops', 'beef-short-ribs',
        'grilled-salmon', 'tom-yum', 'sup-buntut', 'chocolate-lava-cake',
    ];
    $category = MenuCategory::where('slug', 'main-course')->firstOrFail();
    foreach ($retiredSlugs as $slug) {
        MenuItem::factory()->for($category, 'category')->create(['slug' => $slug]);
    }
    $retired = MenuItem::where('slug', 'butter-chicken')->firstOrFail();
    $snapshot = ['menu_item_id' => $retired->id, 'item_name' => 'Butter Chicken', 'unit_price' => 20, 'quantity' => 2];
    $orderItem = Order::factory()->create()->orderItems()->create($snapshot);
    $food = Reservation::factory()->create()->reservationFoods()->create($snapshot);
    $orderBefore = $orderItem->fresh()->getAttributes();
    $foodBefore = $food->fresh()->getAttributes();

    $this->seed(MenuItemSeeder::class);
    $this->seed(MenuItemSeeder::class);

    expect(MenuItem::count())->toBe(35);
    expect(MenuItem::where('is_available', true)->count())->toBe(27);
    foreach ($retiredSlugs as $slug) {
        $this->assertDatabaseHas('menu_items', ['slug' => $slug, 'is_available' => false, 'deleted_at' => null]);
        $this->get(route('menu.show', $slug))->assertNotFound();
    }
    expect($orderItem->fresh()->getAttributes())->toBe($orderBefore);
    expect($food->fresh()->getAttributes())->toBe($foodBefore);
});

test('new dishes remain available to cart reservation selection and Rasa category matching', function () {
    $this->seed([MenuCategorySeeder::class, MenuItemSeeder::class]);
    config()->set('ai.driver', 'mock');
    $item = MenuItem::where('slug', 'ayam-goreng-berempah')->firstOrFail();

    $this->post(route('cart.add'), ['menu_item_id' => $item->id, 'quantity' => 2])
        ->assertSessionHasNoErrors()->assertSessionHas('cart.order.items.'.$item->id.'.quantity', 2);
    $selection = $this->get(route('reservation.table-food'))->assertOk()->viewData('menuItems');
    expect($selection->contains('id', $item->id))->toBeTrue();

    foreach (['appetizers', 'main-course', 'grills', 'soups', 'desserts', 'beverages'] as $category) {
        $categoryName = MenuCategory::query()->where('slug', $category)->value('name');
        $recommendations = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $categoryName])
            ->assertOk()->viewData('recommendations');
        expect($recommendations)->not->toBeEmpty();
        foreach ($recommendations as $recommended) {
            expect($recommended->category->slug)->toBe($category);
            expect($recommended->is_available)->toBeTrue();
        }
    }
});
