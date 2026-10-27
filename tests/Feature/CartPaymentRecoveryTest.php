<?php

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\Payments\MockPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function cartRecoveryOrderCart(MenuItem $item, int $quantity = 1, float $cachedPrice = 0.01): array
{
    return [
        'cart' => [
            'order' => [
                'items' => [
                    $item->id => [
                        'menu_item_id' => $item->id,
                        'quantity' => $quantity,
                        'unit_price' => $cachedPrice,
                    ],
                ],
            ],
        ],
    ];
}

function cartRecoveryOrderData(array $overrides = []): array
{
    return [
        'customer_name' => 'Aida Rahman',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
        'order_type' => 'dine-in',
        ...$overrides,
    ];
}

function cartRecoveryReservationData(Table $table, MenuItem $item): array
{
    return [
        'reservation_type' => 'table_and_food',
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'guest_count' => 2,
        'customer_name' => 'Aida Rahman',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
        'food_items' => [
            $item->id => [
                'menu_item_id' => $item->id,
                'quantity' => 2,
            ],
        ],
    ];
}

test('order and reservation cart contexts stay isolated', function () {
    $orderItem = MenuItem::factory()->create(['price' => 12.50]);
    $reservationItem = MenuItem::factory()->create(['price' => 18.00]);
    $table = Table::factory()->create(['capacity' => 4]);

    $response = $this->withSession(cartRecoveryOrderCart($orderItem))
        ->post(route('reservation.store'), cartRecoveryReservationData($table, $reservationItem));

    $response
        ->assertRedirect()
        ->assertSessionHas('cart.order.items.'.$orderItem->id.'.quantity', 1)
        ->assertSessionHas('cart.reservation.items.'.$reservationItem->id.'.quantity', 2);
});

test('removing an individual order cart item preserves other items and the reservation cart', function () {
    $removedItem = MenuItem::factory()->create();
    $remainingItem = MenuItem::factory()->create();
    $remainingEntry = ['menu_item_id' => $remainingItem->id, 'quantity' => 3];
    $reservationCart = [
        'items' => [
            $removedItem->id => ['menu_item_id' => $removedItem->id, 'quantity' => 5],
        ],
    ];

    $this->withSession([
        'cart.order.items' => [
            $removedItem->id => ['menu_item_id' => $removedItem->id, 'quantity' => 2],
            $remainingItem->id => $remainingEntry,
        ],
        'cart.reservation' => $reservationCart,
    ])->from(route('cart.index'))
        ->delete(route('cart.remove', $removedItem->id))
        ->assertRedirect(route('cart.index'))
        ->assertSessionMissing('cart.order.items.'.$removedItem->id)
        ->assertSessionHas('cart.order.items', [$remainingItem->id => $remainingEntry])
        ->assertSessionHas('cart.reservation', $reservationCart);
});

test('cart and checkout render current database prices instead of cached prices', function () {
    $item = MenuItem::factory()->create(['price' => 10.00]);
    $item->update(['price' => 25.50]);

    $this->withSession(cartRecoveryOrderCart($item, 2, 10.00))
        ->get(route('cart.index'))
        ->assertSee('RM 25.50 each')
        ->assertSee('RM 51.00');

    $this->withSession(cartRecoveryOrderCart($item, 2))
        ->get(route('checkout.create'))
        ->assertSee('RM 51.00');
});

test('checkout uses current prices and ignores client supplied prices', function () {
    config()->set('payment.driver', 'mock');
    $item = MenuItem::factory()->create(['price' => 10.00]);
    $item->update(['price' => 16.75]);

    $this->withSession(cartRecoveryOrderCart($item, 2, 10.00))
        ->post(route('checkout.store'), cartRecoveryOrderData(['total' => 0.01, 'unit_price' => 0.01]))
        ->assertRedirect();

    $order = Order::firstOrFail();

    expect((float) $order->total)->toBe(33.50);
    expect((float) $order->orderItems->first()->unit_price)->toBe(16.75);
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => 33.50]);
});

test('unavailable and deleted cart items are shown clearly and cannot be purchased', function () {
    $unavailableItem = MenuItem::factory()->unavailable()->create();
    $deletedItem = MenuItem::factory()->create();
    $deletedItem->delete();

    $session = [
        'cart' => [
            'order' => [
                'items' => [
                    $unavailableItem->id => ['menu_item_id' => $unavailableItem->id, 'quantity' => 1],
                    $deletedItem->id => ['menu_item_id' => $deletedItem->id, 'quantity' => 1],
                ],
            ],
        ],
    ];

    $this->withSession($session)
        ->get(route('cart.index'))
        ->assertSee('This item is no longer available. Remove it before checkout.')
        ->assertSee('Unavailable menu item');

    $this->withSession($session)
        ->get(route('checkout.create'))
        ->assertRedirect(route('cart.index'))
        ->assertSessionHas('error', 'Remove unavailable items from your cart before checking out.');

    $this->withSession($session)
        ->post(route('checkout.store'), cartRecoveryOrderData())
        ->assertSessionHasErrors('cart');

    $this->assertDatabaseCount('orders', 0);
});

test('cart totals include only current purchasable items while checkout remains blocked', function () {
    $availableItem = MenuItem::factory()->create(['price' => 12.50]);
    $unavailableItem = MenuItem::factory()->unavailable()->create(['price' => 100.00]);
    $session = [
        'cart' => [
            'order' => [
                'items' => [
                    $availableItem->id => ['menu_item_id' => $availableItem->id, 'quantity' => 2],
                    $unavailableItem->id => ['menu_item_id' => $unavailableItem->id, 'quantity' => 1],
                ],
            ],
        ],
    ];

    $this->withSession($session)
        ->get(route('cart.index'))
        ->assertSee('RM 25.00')
        ->assertDontSee('RM 125.00')
        ->assertSee('Remove unavailable items before checking out.');
});

test('a failed order payment retries the existing pending order without clearing its cart', function () {
    config()->set('payment.driver', 'mock');
    $order = Order::factory()->create(['status' => 'pending', 'total' => 24.00]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => 'failed',
        'amount' => 24.00,
    ]);
    $originalReference = $payment->provider_reference;
    $item = MenuItem::factory()->create();

    $this->withSession(cartRecoveryOrderCart($item))
        ->post(route('payment.retry', $payment))
        ->assertRedirect(route('payment.create', $payment));

    expect(Order::count())->toBe(1);
    expect(Payment::count())->toBe(1);
    expect($payment->refresh()->status)->toBe('pending');
    expect($payment->provider_reference)->not->toBe($originalReference);
});

test('a failed table and food reservation payment retries the existing pending reservation', function () {
    config()->set('payment.driver', 'mock');
    $reservation = Reservation::factory()->withFood()->create(['status' => 'pending']);
    $item = MenuItem::factory()->create(['price' => 15.00]);
    $reservation->reservationFoods()->create([
        'menu_item_id' => $item->id,
        'item_name' => $item->name,
        'unit_price' => 15.00,
        'quantity' => 2,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->create(['status' => 'failed']);

    $this->withSession([
        'cart' => ['reservation' => ['items' => [$item->id => ['menu_item_id' => $item->id, 'quantity' => 2]]]],
    ])
        ->post(route('payment.retry', $payment))
        ->assertRedirect(route('payment.create', $payment));

    expect(Reservation::count())->toBe(1);
    expect(Payment::count())->toBe(1);
    expect($payment->refresh()->status)->toBe('pending');
});

test('a retry is rejected when the payment amount no longer matches its pending order', function () {
    $order = Order::factory()->create(['status' => 'pending', 'total' => 25.00]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => 'failed',
        'amount' => 20.00,
    ]);

    $this->post(route('payment.retry', $payment))
        ->assertRedirect(route('payment.failed', $payment))
        ->assertSessionHasErrors('payment');

    expect($payment->refresh()->status)->toBe('failed');
});

test('an unknown payment retry reference returns not found', function () {
    $this->post(route('payment.retry', 999999))->assertNotFound();
});

test('paid and progressed transactions cannot be retried', function (string $paymentStatus, string $orderStatus) {
    $order = Order::factory()->create(['status' => $orderStatus, 'total' => 20.00]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status' => $paymentStatus,
        'amount' => 20.00,
    ]);
    $reference = $payment->provider_reference;

    $this->post(route('payment.retry', $payment))
        ->assertRedirect(route('payment.failed', $payment))
        ->assertSessionHasErrors('payment');

    expect($payment->refresh()->status)->toBe($paymentStatus);
    expect($payment->provider_reference)->toBe($reference);
})->with([
    'paid payment' => ['paid', 'pending'],
    'confirmed order' => ['failed', 'confirmed'],
    'completed order' => ['failed', 'completed'],
    'cancelled order' => ['failed', 'cancelled'],
]);

test('successful payment clears only the matching cart context', function () {
    Mail::fake();
    config()->set('payment.driver', 'mock');
    $order = Order::factory()->create(['status' => 'pending', 'total' => 20.00]);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => 20.00]);
    $orderItem = MenuItem::factory()->create();
    $reservationItem = MenuItem::factory()->create();

    $this->withSession([
        ...cartRecoveryOrderCart($orderItem),
        'cart' => [
            'order' => ['items' => [$orderItem->id => ['menu_item_id' => $orderItem->id, 'quantity' => 1]]],
            'reservation' => ['items' => [$reservationItem->id => ['menu_item_id' => $reservationItem->id, 'quantity' => 1]]],
        ],
    ])
        ->post(route('payment.callback'), app(MockPaymentService::class)->callbackPayload($payment, 'success'))
        ->assertRedirect()
        ->assertSessionMissing('cart.order')
        ->assertSessionHas('cart.reservation.items.'.$reservationItem->id.'.quantity', 1);
});
