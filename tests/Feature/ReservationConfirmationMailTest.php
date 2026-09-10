<?php

use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('table_and_food confirmation mail includes QR, signed cancel and receipt links, total and payment reference', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->withFood()->confirmed()->create();
    $menuItem = MenuItem::factory()->create();
    $reservation->reservationFoods()->create([
        'menu_item_id' => $menuItem->id,
        'item_name' => 'Satay Skewers',
        'unit_price' => 10.00,
        'quantity' => 2,
    ]);
    Payment::factory()->forReservation($reservation)->paid()->create([
        'provider_reference' => 'PAY-2026-TESTREF01',
    ]);
    $reservation->refresh()->load('reservationFoods', 'payment', 'table');

    $html = (new ReservationConfirmationMail($reservation))->render();

    expect($html)
        ->toContain('data:image/svg+xml;base64')
        ->toContain('/reservation/'.$reservation->id.'/cancel?')
        ->toContain('/reservation/'.$reservation->id.'/receipt?')
        ->toContain('RM 20.00')
        ->toContain('PAY-2026-TESTREF01');
});

test('table_only confirmation mail has QR and cancel link but no receipt or payment reference', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->confirmed()->create();
    $reservation->refresh()->load('reservationFoods', 'payment', 'table');

    $html = (new ReservationConfirmationMail($reservation))->render();

    expect($html)
        ->toContain('data:image/svg+xml;base64')
        ->toContain('/reservation/'.$reservation->id.'/cancel?')
        ->not->toContain('/receipt?');
});
