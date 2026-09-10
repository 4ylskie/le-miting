<?php

use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('a tampered signed cancellation link renders the invalid link page', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->create();

    $this->get(route('reservation.cancel-form', $reservation)
        .'?expires='.now()->addMinutes(5)->timestamp.'&signature=invalid')
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');
});

test('an expired signed cancellation link renders the invalid link page', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->create();

    $url = URL::temporarySignedRoute('reservation.cancel-form', now()->subHour(), ['reservation' => $reservation->id]);

    $this->get($url)
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');
});

test('an unsigned order receipt link renders the invalid link page', function () {
    $order = Order::factory()->create();

    $this->get('/orders/'.$order->id.'/receipt')
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');
});
