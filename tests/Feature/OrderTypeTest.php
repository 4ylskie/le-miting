<?php

use App\Mail\OrderConfirmationMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function orderTypeCart(MenuItem $menuItem): array
{
    return [
        'cart' => [
            'order' => [
                'items' => [
                    $menuItem->id => [
                        'menu_item_id' => $menuItem->id,
                        'quantity' => 1,
                    ],
                ],
            ],
        ],
    ];
}

function checkoutOrderData(string $orderType): array
{
    return [
        'customer_name' => 'Aida Rahman',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
        'order_type' => $orderType,
    ];
}

test('checkout persists dine-in as the canonical order type', function () {
    $menuItem = MenuItem::factory()->create(['price' => 12.50]);

    $this->withSession(orderTypeCart($menuItem))
        ->post(route('checkout.store'), checkoutOrderData('dine-in'))
        ->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'customer_email' => 'aida@example.com',
        'order_type' => 'dine-in',
    ]);
});

test('checkout persists takeaway as the canonical order type', function () {
    $menuItem = MenuItem::factory()->create(['price' => 12.50]);

    $this->withSession(orderTypeCart($menuItem))
        ->post(route('checkout.store'), checkoutOrderData('takeaway'))
        ->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'customer_email' => 'aida@example.com',
        'order_type' => 'takeaway',
    ]);
});

test('checkout rejects the legacy dine_in order type', function () {
    $this->post(route('checkout.store'), checkoutOrderData('dine_in'))
        ->assertSessionHasErrors('order_type');

    $this->assertDatabaseCount('orders', 0);
});

test('the compatibility migration converts existing dine_in orders', function () {
    $order = Order::factory()->create(['order_type' => 'dine_in']);

    $migration = require database_path('migrations/2026_09_16_120852_normalize_dine_in_order_types.php');
    $migration->up();

    expect($order->refresh()->order_type)->toBe('dine-in');
});

test('dine-in is displayed in order confirmation email and receipt', function () {
    $order = Order::factory()->create(['order_type' => 'dine-in']);
    Payment::factory()->create(['order_id' => $order->id, 'status' => 'paid']);
    $order->refresh()->load('orderItems', 'payment');

    $mailHtml = (new OrderConfirmationMail($order))->render();
    $receiptHtml = view('pdfs.receipt', ['order' => $order])->render();

    expect($mailHtml)
        ->toContain('Dine-in')
        ->toContain('Served at your table');

    expect($receiptHtml)->toContain('Dine-in');
});
