<?php

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('an order confirmation cannot be accessed with a raw ID', function () {
    $order = Order::factory()->create(['status' => 'confirmed']);

    $this->get(route('orders.confirmation', $order))
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');
});

test('a valid signed order confirmation link displays the confirmed order', function () {
    $order = Order::factory()->create(['status' => 'confirmed']);
    $url = URL::temporarySignedRoute('orders.confirmation', now()->addHours(24), ['order' => $order]);

    $this->get($url)
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order->customer_email);
});

test('a reservation confirmation cannot be accessed with a raw ID', function () {
    $reservation = Reservation::factory()->confirmed()->create();

    $this->get(route('reservation.confirmation', $reservation))
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');
});

test('a valid signed reservation confirmation link displays the confirmed reservation', function () {
    $reservation = Reservation::factory()->confirmed()->create();
    $url = URL::temporarySignedRoute('reservation.confirmation', now()->addHours(24), ['reservation' => $reservation]);

    $this->get($url)
        ->assertOk()
        ->assertSee($reservation->reservation_number)
        ->assertSee($reservation->customer_email);
});

test('an expired confirmation link displays the invalid link page', function () {
    $order = Order::factory()->create(['status' => 'confirmed']);
    $url = URL::temporarySignedRoute('orders.confirmation', now()->subHour(), ['order' => $order]);

    $this->get($url)
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');
});

test('a table-only reservation redirects to a valid signed confirmation link', function () {
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);

    $response = $this->post(route('reservation.store'), [
        'reservation_type' => 'table_only',
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'guest_count' => 2,
        'customer_name' => 'Ada',
        'customer_email' => 'ada@example.com',
        'customer_phone' => '0123456789',
    ]);

    $response->assertRedirect();
    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee(Reservation::firstOrFail()->reservation_number);
});
