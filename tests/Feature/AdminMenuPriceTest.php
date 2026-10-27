<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('menu price requests accept zero to two decimal places without changing the amount', function (string $operation, string $price, string $stored) {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();
    $item = $operation === 'update'
        ? MenuItem::factory()->for($category, 'category')->create(['price' => '20.00'])
        : null;
    $payload = [
        'name' => 'Precision fixture',
        'slug' => $item?->slug ?? 'precision-fixture',
        'menu_category_id' => $category->id,
        'description' => 'A price validation fixture.',
        'price' => $price,
        'is_available' => '1',
        'is_featured' => '0',
    ];

    $this->actingAs($admin);
    $response = $item
        ? $this->patch(route('admin.menu.items.update', $item), $payload)
        : $this->post(route('admin.menu.items.store'), $payload);
    $response->assertRedirect(route('admin.menu.items.index'))->assertSessionHasNoErrors();

    $saved = MenuItem::where('slug', $payload['slug'])->firstOrFail();
    expect($saved->getRawOriginal('price'))->toBe($stored);
    expect($saved->price)->toBe($stored);
})->with(['create', 'update'])->with([
    'zero' => ['0', '0.00'],
    'one sen' => ['0.01', '0.01'],
    'whole ringgit' => ['12', '12.00'],
    'one decimal' => ['12.5', '12.50'],
    'two decimals' => ['12.34', '12.34'],
    'maximum' => ['99999', '99999.00'],
    'maximum with decimals' => ['99999.00', '99999.00'],
]);

test('menu price requests reject unsupported precision and out of range amounts without saving', function (string $operation, string $price, string $message) {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();
    $item = $operation === 'update'
        ? MenuItem::factory()->for($category, 'category')->create(['price' => '20.00'])
        : null;
    $original = $item?->fresh()->getAttributes();
    $formRoute = $item ? route('admin.menu.items.edit', $item) : route('admin.menu.items.create');
    $payload = [
        'name' => 'Rejected price fixture',
        'slug' => $item?->slug ?? 'rejected-price-fixture',
        'menu_category_id' => $category->id,
        'description' => 'This request must not be saved.',
        'price' => $price,
        'is_available' => '1',
        'is_featured' => '0',
    ];

    $this->actingAs($admin)->from($formRoute);
    $response = $item
        ? $this->patch(route('admin.menu.items.update', $item), $payload)
        : $this->post(route('admin.menu.items.store'), $payload);
    $response->assertRedirect($formRoute)->assertSessionHasErrors(['price' => $message])
        ->assertSessionHasInput('price', $price);

    $this->assertDatabaseCount('menu_items', $item ? 1 : 0);
    if ($item) {
        expect($item->fresh()->getAttributes())->toBe($original);
    }
})->with(['create', 'update'])->with([
    'would round down' => ['12.344', 'The price must have no more than two decimal places.'],
    'would round up' => ['12.345', 'The price must have no more than two decimal places.'],
    'trailing extra zero' => ['12.500', 'The price must have no more than two decimal places.'],
    'fraction of a sen' => ['0.001', 'The price must have no more than two decimal places.'],
    'scientific notation' => ['1e1', 'The price must have no more than two decimal places.'],
    'below minimum' => ['-0.01', 'The price field must be at least 0.'],
    'above maximum' => ['99999.01', 'The price field must not be greater than 99999.'],
]);
