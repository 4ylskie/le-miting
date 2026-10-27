<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('category creation preserves omission defaults and explicit boolean values', function (array $active, bool $expected) {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.menu.categories.store'), [
        'name' => 'Active policy fixture', 'slug' => 'active-policy-fixture', ...$active,
    ])->assertRedirect(route('admin.menu.categories.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('menu_categories', ['slug' => 'active-policy-fixture', 'is_active' => $expected]);
})->with([
    'omitted' => [[], true],
    'explicit zero' => [['is_active' => '0'], false],
    'explicit one' => [['is_active' => '1'], true],
]);

test('category updates preserve omission and accept explicit boolean values', function (bool $initial, array $active, bool $expected) {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create(['is_active' => $initial]);

    $this->actingAs($admin)->patch(route('admin.menu.categories.update', $category), [
        'name' => 'Updated policy fixture', 'slug' => $category->slug, ...$active,
    ])->assertRedirect(route('admin.menu.categories.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('menu_categories', ['id' => $category->id, 'name' => 'Updated policy fixture', 'is_active' => $expected]);
})->with([
    'omitted active' => [true, [], true],
    'omitted inactive' => [false, [], false],
    'explicit zero' => [true, ['is_active' => '0'], false],
    'explicit one' => [false, ['is_active' => '1'], true],
]);

test('invalid category active values cannot create or update records', function (mixed $invalid) {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create(['is_active' => true]);
    $original = $category->fresh()->getAttributes();

    $this->actingAs($admin)->post(route('admin.menu.categories.store'), [
        'name' => 'Invalid active fixture', 'slug' => 'invalid-active-fixture', 'is_active' => $invalid,
    ])->assertSessionHasErrors('is_active');
    $this->patch(route('admin.menu.categories.update', $category), [
        'name' => 'Invalid update fixture', 'slug' => $category->slug, 'is_active' => $invalid,
    ])->assertSessionHasErrors('is_active');

    $this->assertDatabaseCount('menu_categories', 1);
    expect($category->fresh()->getAttributes())->toBe($original);
})->with([
    'word' => ['yes'],
    'false string' => ['false'],
    'out of range' => [2],
    'array' => [['0', '1']],
    'null' => [null],
]);

test('category activation controls visibility without changing items or historical transactions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create(['is_active' => true]);
    $item = MenuItem::factory()->for($category, 'category')->create(['is_available' => true]);
    $unavailable = MenuItem::factory()->for($category, 'category')->create(['is_available' => false]);
    $order = Order::factory()->create(['status' => 'completed']);
    $reservation = Reservation::factory()->withFood()->create(['status' => 'completed']);
    $snapshot = ['menu_item_id' => $item->id, 'item_name' => 'Historical dish', 'unit_price' => '12.50', 'quantity' => 2];
    $orderItem = $order->orderItems()->create($snapshot);
    $reservationFood = $reservation->reservationFoods()->create($snapshot);
    $records = collect([$item, $unavailable, $order, $reservation, $orderItem, $reservationFood]);
    $original = $records->map(fn ($record) => $record->fresh()->getAttributes())->all();

    $this->actingAs($admin)->patch(route('admin.menu.categories.update', $category), [
        'name' => $category->name, 'slug' => $category->slug, 'is_active' => '0',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.menu.categories.index'));

    $response = $this->get(route('menu.index'))->assertOk();
    expect($response->viewData('items')->pluck('id')->all())->toBe([]);
    expect($response->viewData('categories')->pluck('id')->all())->toBe([]);
    $this->get(route('menu.show', $item))->assertNotFound();
    expect($records->map(fn ($record) => $record->fresh()->getAttributes())->all())->toBe($original);
    $this->delete(route('admin.menu.categories.destroy', $category))->assertSessionHasErrors('category');
    $this->assertModelExists($category);

    $this->patch(route('admin.menu.categories.update', $category), [
        'name' => $category->name, 'slug' => $category->slug, 'is_active' => '1',
    ])->assertRedirect(route('admin.menu.categories.index'));

    $response = $this->get(route('menu.index'))->assertOk();
    expect($response->viewData('items')->pluck('id')->all())->toBe([$item->id]);
    expect($response->viewData('categories')->pluck('id')->all())->toBe([$category->id]);
    $this->get(route('menu.show', $item))->assertOk();
    $this->get(route('menu.show', $unavailable))->assertNotFound();
    expect($records->map(fn ($record) => $record->fresh()->getAttributes())->all())->toBe($original);
});

test('an administrator can create a category', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.menu.categories.store'), [
            'name' => 'Desserts',
            'slug' => 'desserts',
            'sort_order' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.menu.categories.index'));

    $this->assertDatabaseHas('menu_categories', ['name' => 'Desserts', 'slug' => 'desserts']);
});

test('a duplicate category slug is rejected on update', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    MenuCategory::factory()->create(['name' => 'Starters', 'slug' => 'starters']);
    $category = MenuCategory::factory()->create(['name' => 'Mains', 'slug' => 'mains']);

    $this->actingAs($admin)
        ->patch(route('admin.menu.categories.update', $category), [
            'name' => 'Mains',
            'slug' => 'starters',
        ])
        ->assertSessionHasErrors('slug');

    $this->assertDatabaseHas('menu_categories', ['slug' => 'mains']);
});

test('a category with menu items cannot be deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();
    MenuItem::factory()->create(['menu_category_id' => $category->id]);

    $this->actingAs($admin)
        ->delete(route('admin.menu.categories.destroy', $category))
        ->assertSessionHasErrors('category');

    $this->assertDatabaseHas('menu_categories', ['id' => $category->id]);
});

test('an empty category can be deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.menu.categories.destroy', $category))
        ->assertRedirect(route('admin.menu.categories.index'));

    $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
});

test('an administrator can create a menu item', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.menu.items.store'), [
            'name' => 'Nasi Lemak',
            'slug' => 'nasi-lemak',
            'menu_category_id' => $category->id,
            'description' => 'Fragrant coconut rice.',
            'price' => 12.50,
            'is_available' => 1,
            'is_featured' => 1,
        ])
        ->assertRedirect(route('admin.menu.items.index'));

    $this->assertDatabaseHas('menu_items', ['slug' => 'nasi-lemak', 'price' => 12.50, 'is_available' => 1, 'is_featured' => 1]);
});

test('a menu item image is stored on the public disk', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.menu.items.store'), [
            'name' => 'Roti Canai',
            'slug' => 'roti-canai',
            'menu_category_id' => $category->id,
            'price' => 3.50,
            'description' => 'Flaky flatbread served with dhal.',
            'image' => UploadedFile::fake()->image('roti.jpg'),
            'is_available' => 1,
            'is_featured' => 0,
        ])
        ->assertRedirect(route('admin.menu.items.index'));

    $item = MenuItem::where('slug', 'roti-canai')->firstOrFail();

    expect($item->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($item->image_path);
});

test('an administrator can update a menu item', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = MenuItem::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.menu.items.update', $item), [
            'name' => $item->name,
            'slug' => $item->slug,
            'menu_category_id' => $item->menu_category_id,
            'description' => $item->description,
            'price' => 25.00,
            'is_available' => 0,
            'is_featured' => 0,
        ])
        ->assertRedirect(route('admin.menu.items.index'));

    expect($item->refresh()->price)->toBe('25.00');
    expect($item->is_available)->toBeFalse();
    expect($item->is_featured)->toBeFalse();
});

test('an administrator can remove a menu item without deleting historical order snapshots', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = MenuItem::factory()->create();
    $order = Order::factory()->create();
    $order->orderItems()->create([
        'menu_item_id' => $item->id,
        'item_name' => 'Historical '.$item->name,
        'unit_price' => 12.50,
        'quantity' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.menu.items.destroy', $item))
        ->assertRedirect(route('admin.menu.items.index'));

    $this->assertSoftDeleted('menu_items', ['id' => $item->id]);
    expect($order->fresh('orderItems')->orderItems->first()->item_name)->toBe('Historical '.$item->name);
});

test('an administrator can replace a menu image without retaining the old file', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $item = MenuItem::factory()->create(['image_path' => 'menu/old-image.jpg']);
    Storage::disk('public')->put($item->image_path, 'old image');

    $this->actingAs($admin)
        ->patch(route('admin.menu.items.update', $item), [
            'name' => $item->name,
            'slug' => $item->slug,
            'menu_category_id' => $item->menu_category_id,
            'description' => $item->description,
            'price' => $item->price,
            'image' => UploadedFile::fake()->image('replacement.png', 800, 600),
            'is_available' => 1,
            'is_featured' => 0,
        ])
        ->assertRedirect(route('admin.menu.items.index'));

    Storage::disk('public')->assertMissing('menu/old-image.jpg');
    Storage::disk('public')->assertExists($item->fresh()->image_path);
});

test('non-images oversized images and oversized dimensions are rejected', function (UploadedFile $image) {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.menu.items.store'), [
            'name' => 'Upload Test',
            'slug' => 'upload-test',
            'menu_category_id' => $category->id,
            'description' => 'A test dish.',
            'price' => 12.50,
            'image' => $image,
            'is_available' => 1,
            'is_featured' => 0,
        ])
        ->assertSessionHasErrors('image');
})->with([
    'non-image' => UploadedFile::fake()->create('malicious.php', 100, 'application/x-php'),
    'oversized file' => UploadedFile::fake()->image('large.jpg')->size(2049),
    'oversized dimensions' => UploadedFile::fake()->image('wide.png', 4097, 600),
]);

test('an administrator can toggle a menu item availability', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = MenuItem::factory()->create(['is_available' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.menu.items.toggle-availability', $item))
        ->assertRedirect();

    expect($item->refresh()->is_available)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.menu.items.toggle-availability', $item))
        ->assertRedirect();

    expect($item->refresh()->is_available)->toBeTrue();
});

test('only available featured items appear on the customer home page', function () {
    $available = MenuItem::factory()->featured()->create();
    $unavailable = MenuItem::factory()->featured()->unavailable()->create();
    $deleted = MenuItem::factory()->featured()->create();
    $deleted->delete();

    $this->get(route('home'))
        ->assertSee($available->name)
        ->assertDontSee($unavailable->name)
        ->assertDontSee($deleted->name);
});

test('the menu items index lists items with category', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create(['name' => 'Breakfast']);
    $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);

    $this->actingAs($admin)
        ->get(route('admin.menu.items.index'))
        ->assertOk()
        ->assertSee($item->name)
        ->assertSee('Breakfast');
});
