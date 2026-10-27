<?php

use App\Models\MenuItem;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest can add an available menu item to the cart', function () {
    $item = MenuItem::factory()->create();

    $this->post(route('cart.add'), ['menu_item_id' => $item->id, 'quantity' => 2])
        ->assertRedirect()
        ->assertSessionHas('cart.order.items.'.$item->id.'.quantity', 2);
});

test('a guest cannot add a soft deleted menu item to the cart', function () {
    $item = MenuItem::factory()->create();
    $item->delete();

    $this->post(route('cart.add'), ['menu_item_id' => $item->id, 'quantity' => 1])
        ->assertSessionHasErrors('menu_item_id');
});

test('cart quantity validation remains enforced', function () {
    $item = MenuItem::factory()->create();

    $this->patch(route('cart.update', $item->id), ['quantity' => 0])
        ->assertSessionHasErrors('quantity');
});

test('contact enquiries retain their validation requirements', function () {
    $this->post(route('contact.store'), ['name' => 'Ada'])
        ->assertSessionHasErrors(['email', 'message']);
});

test('reservation availability validates guest input before querying tables', function () {
    $this->get(route('reservation.availability', [
        'reservation_type' => 'invalid',
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'guest_count' => 2,
    ]))
        ->assertSessionHasErrors('reservation_type');
});

test('a guest can check reservation availability with valid input', function () {
    Table::factory()->create(['capacity' => 4, 'status' => 'available']);

    $this->get(route('reservation.availability', [
        'reservation_type' => 'table_only',
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'guest_count' => 2,
    ]))
        ->assertOk();
});

test('a guest cannot pre-order a soft deleted menu item', function () {
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $item = MenuItem::factory()->create();
    $item->delete();

    $this->post(route('reservation.store'), [
        'reservation_type' => 'table_and_food',
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'guest_count' => 2,
        'customer_name' => 'Ada',
        'customer_email' => 'ada@example.com',
        'customer_phone' => '0123456789',
        'food_items' => [
            ['menu_item_id' => $item->id, 'quantity' => 1],
        ],
    ])
        ->assertSessionHasErrors('food_items.0.menu_item_id');
});
