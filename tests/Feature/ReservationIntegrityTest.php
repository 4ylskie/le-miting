<?php

use App\Mail\ReservationCancellationMail;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function tableOnlyReservationData(Table $table, array $overrides = []): array
{
    return [
        'reservation_type' => 'table_only',
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'guest_count' => 2,
        'customer_name' => 'Aida Rahman',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
        ...$overrides,
    ];
}

test('a crafted request cannot reserve a maintenance table', function () {
    Mail::fake();
    $table = Table::factory()->create(['status' => 'maintenance', 'capacity' => 4]);

    $this->post(route('reservation.store'), tableOnlyReservationData($table))
        ->assertSessionHasErrors([
            'table_id' => 'That table is no longer available. Please select another table.',
        ]);

    $this->assertDatabaseCount('reservations', 0);
});

test('capacity is revalidated during final reservation creation', function () {
    Mail::fake();
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 2]);

    $this->post(route('reservation.store'), tableOnlyReservationData($table, ['guest_count' => 3]))
        ->assertSessionHasErrors([
            'guest_count' => 'This table only seats 2 guests. Please choose a larger table.',
        ]);

    $this->assertDatabaseCount('reservations', 0);
});

test('an overlapping reservation is rejected during final creation', function () {
    Mail::fake();
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    Reservation::factory()->confirmed()->create([
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'duration_minutes' => 120,
    ]);

    $this->post(route('reservation.store'), tableOnlyReservationData($table, ['reservation_time' => '12:00']))
        ->assertSessionHasErrors('table_id');

    $this->assertDatabaseCount('reservations', 1);
});

test('the authoritative booking path rejects a second overlapping reservation', function () {
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $service = app(ReservationService::class);

    $service->createTableOnly(tableOnlyReservationData($table));

    expect(fn () => $service->createTableOnly(tableOnlyReservationData($table, ['reservation_time' => '12:00'])))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('reservations', 1);
});

test('an expired pending hold does not block table availability', function () {
    $table = Table::factory()->create(['status' => 'available']);
    $start = Carbon::tomorrow('Asia/Kuala_Lumpur')->setTime(11, 0);
    Reservation::factory()->create([
        'table_id' => $table->id,
        'reservation_date' => $start->toDateString(),
        'reservation_time' => '11:00',
        'status' => 'pending',
        'created_at' => now()->subMinutes(16),
    ]);

    expect(app(ReservationService::class)->isTableAvailable($table, $start))->toBeTrue();
});

test('an active pending hold blocks table availability', function () {
    $table = Table::factory()->create(['status' => 'available']);
    $start = Carbon::tomorrow('Asia/Kuala_Lumpur')->setTime(11, 0);
    Reservation::factory()->create([
        'table_id' => $table->id,
        'reservation_date' => $start->toDateString(),
        'reservation_time' => '11:00',
        'status' => 'pending',
        'created_at' => now()->subMinutes(14),
    ]);

    expect(app(ReservationService::class)->isTableAvailable($table, $start))->toBeFalse();
});

test('a future reservation can be cancelled with a valid signed link', function () {
    Mail::fake();
    config(['app.url' => 'http://localhost']);
    $reservation = Reservation::factory()->confirmed()->create();
    $url = URL::temporarySignedRoute('reservation.cancel', now()->addHours(24), ['reservation' => $reservation->id]);

    $this->post($url)
        ->assertOk()
        ->assertSee('Reservation Cancelled');

    expect($reservation->refresh()->status)->toBe('cancelled');
    Mail::assertSent(ReservationCancellationMail::class, 1);
});

test('a reservation cannot be cancelled after its start time', function () {
    config(['app.url' => 'http://localhost']);
    $this->travelTo('2026-09-16 12:00:00');
    $reservation = Reservation::factory()->confirmed()->create([
        'reservation_date' => '2026-09-16',
        'reservation_time' => '11:59',
    ]);
    $url = URL::temporarySignedRoute('reservation.cancel', now()->addHours(24), ['reservation' => $reservation->id]);

    $this->post($url)
        ->assertUnprocessable()
        ->assertSee('This reservation can no longer be cancelled online.');

    expect($reservation->refresh()->status)->toBe('confirmed');
});
