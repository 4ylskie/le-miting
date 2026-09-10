<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

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
        ])
        ->assertRedirect(route('admin.menu.items.index'));

    $this->assertDatabaseHas('menu_items', ['slug' => 'nasi-lemak', 'price' => 12.50, 'is_available' => 1]);
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
        ])
        ->assertRedirect(route('admin.menu.items.index'));

    expect($item->refresh()->price)->toBe('25.00');
});

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
