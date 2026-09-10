<?php

use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('a signed link downloads the food pre-order receipt PDF for a paid table_and_food reservation', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->withFood()->confirmed()->create();
    $menuItem = MenuItem::factory()->create();
    $reservation->reservationFoods()->create([
        'menu_item_id' => $menuItem->id,
        'item_name' => 'Satay Skewers',
        'unit_price' => 10.00,
        'quantity' => 2,
    ]);
    Payment::factory()->forReservation($reservation)->paid()->create();
    $reservation->refresh()->load('table', 'payment');

    $url = URL::temporarySignedRoute('reservation.receipt', now()->addHours(24), ['reservation' => $reservation->id]);

    $response = $this->get($url);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('receipt is not available for table_only reservations', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->confirmed()->create();
    $reservation->refresh()->load('payment');

    $url = URL::temporarySignedRoute('reservation.receipt', now()->addHours(24), ['reservation' => $reservation->id]);

    $this->get($url)->assertNotFound();
});

test('receipt is not available when the payment was never verified', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->withFood()->create(['status' => 'pending']);
    $menuItem = MenuItem::factory()->create();
    $reservation->reservationFoods()->create([
        'menu_item_id' => $menuItem->id,
        'item_name' => 'Satay Skewers',
        'unit_price' => 10.00,
        'quantity' => 1,
    ]);
    Payment::factory()->forReservation($reservation)->create();

    $url = URL::temporarySignedRoute('reservation.receipt', now()->addHours(24), ['reservation' => $reservation->id]);

    $this->get($url)->assertNotFound();
});
