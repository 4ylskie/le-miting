<?php

use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\Payments\MockPaymentService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\NonTransactionalDatabase;

uses(NonTransactionalDatabase::class);

/** @param array<string, mixed> $attributes */
function integrityReservation(Table $table, array $attributes = []): Reservation
{
    return Reservation::factory()->create(array_merge([
        'table_id' => $table->id, 'reservation_type' => 'table_only', 'guest_count' => 2,
        'reservation_date' => '2026-10-09', 'reservation_time' => '11:00:00',
        'duration_minutes' => 120, 'status' => 'confirmed', 'created_at' => now(),
    ], $attributes))->refresh();
}

/** @return array<string, mixed> */
function integrityTablePayload(Table $table, array $changes = []): array
{
    return array_merge([
        'table_number' => $table->table_number, 'capacity' => $table->capacity, 'status' => $table->status,
        'expected_capacity' => $table->capacity, 'expected_status' => $table->status,
    ], $changes);
}

/** @return array<string, mixed> */
function integrityAssignmentPayload(Reservation $reservation, Table $destination): array
{
    return ['table_id' => $destination->id, 'expected_table_id' => $reservation->table_id, 'expected_status' => $reservation->status];
}

test('approved capacity classes can be created and edited', function (int $capacity) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('admin.tables.store'), ['table_number' => 'Capacity-test', 'capacity' => $capacity, 'status' => 'available'])
        ->assertRedirect(route('admin.tables.index'))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tables', ['table_number' => 'Capacity-test', 'capacity' => $capacity]);
    $table = Table::factory()->create(['capacity' => 4]);

    $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['capacity' => $capacity]))
        ->assertRedirect(route('admin.tables.index'))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => $capacity]);
})->with([2, 3, 4, 6, 8, 10]);

test('unsupported capacities are rejected without partial table updates', function (int $capacity) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('admin.tables.store'), ['table_number' => 'Invalid-capacity', 'capacity' => $capacity, 'status' => 'available'])
        ->assertSessionHasErrors('capacity');
    $this->assertDatabaseMissing('tables', ['table_number' => 'Invalid-capacity']);
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $before = $table->refresh()->getAttributes();

    $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['table_number' => 'Changed', 'capacity' => $capacity, 'status' => 'maintenance']))
        ->assertSessionHasErrors('capacity');
    expect($table->refresh()->getAttributes())->toBe($before);
})->with([0, 1, 5, 7, 9, 12, 20]);

test('reassignment enforces both suitability boundaries', function (int $capacity, int $guests, bool $allowed) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $source = Table::factory()->create(['capacity' => 10]);
    $target = Table::factory()->create(['capacity' => $capacity, 'status' => 'available']);
    $reservation = integrityReservation($source, ['guest_count' => $guests, 'status' => 'pending', 'created_at' => now()->subHour()]);
    $before = $reservation->getAttributes();
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-table', $reservation), integrityAssignmentPayload($reservation, $target));

    if ($allowed) {
        $response->assertSessionHasNoErrors()->assertSessionHas('status');
    } else {
        $response->assertSessionHasErrors(['table_id' => 'That table is not suitable for this party size. Choose a suitable table.']);
    }
    $after = $reservation->refresh()->getAttributes();
    expect($after['table_id'])->toBe($allowed ? $target->id : $source->id);
    unset($before['table_id'], $before['updated_at'], $after['table_id'], $after['updated_at']);
    expect($after)->toBe($before);
    Mail::assertNothingSent();
})->with([
    '2 minimum' => [2, 1, true], '2 maximum' => [2, 2, true], '2 too many' => [2, 3, false],
    '3 minimum' => [3, 1, true], '3 maximum' => [3, 3, true], '3 too many' => [3, 4, false],
    '4 minimum' => [4, 1, true], '4 maximum' => [4, 4, true], '4 too many' => [4, 5, false],
    '6 one guest' => [6, 1, false], '6 too few' => [6, 2, false], '6 minimum' => [6, 3, true], '6 maximum' => [6, 6, true], '6 too many' => [6, 7, false],
    '8 too few' => [8, 4, false], '8 minimum' => [8, 5, true], '8 maximum' => [8, 8, true], '8 too many' => [8, 9, false],
    '10 too few' => [10, 6, false], '10 minimum' => [10, 7, true], '10 maximum' => [10, 10, true], '10 too many' => [10, 11, false],
]);

test('management protects precisely the approved commitment states', function (string $status, string $date, string $time, int $age, bool $paid, bool $protected) {
    $this->travelTo('2026-10-09 03:00:00');
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $reservation = integrityReservation($table, [
        'status' => $status, 'reservation_date' => $date, 'reservation_time' => $time,
        'created_at' => now()->subSeconds($age), 'reservation_type' => $status === 'pending' ? 'table_and_food' : 'table_only',
    ]);
    if ($paid) {
        Payment::factory()->forReservation($reservation)->paid()->create();
    }
    $before = $table->refresh()->getAttributes();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $response = $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['status' => 'maintenance', 'table_number' => 'Edited']));
    if ($protected) {
        $response->assertSessionHasErrors('status');
        expect($table->refresh()->getAttributes())->toBe($before);
        $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['capacity' => 6, 'table_number' => 'Edited']))
            ->assertSessionHasErrors('capacity');
        expect($table->refresh()->getAttributes())->toBe($before);
    } else {
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'status' => 'maintenance', 'table_number' => 'Edited']);
        $table->refresh();
        $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['capacity' => 6]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => 6]);
    }
})->with([
    'ongoing confirmed' => ['confirmed', '2026-10-09', '10:00', 0, false, true],
    'future confirmed' => ['confirmed', '2026-10-10', '11:00', 0, false, true],
    'past unresolved confirmed' => ['confirmed', '2026-10-08', '08:00', 0, false, true],
    'completed one second before end' => ['completed', '2026-10-09', '09:00:01', 0, false, true],
    'completed exact end Malaysia time' => ['completed', '2026-10-09', '09:00:00', 0, false, false],
    'completed ended' => ['completed', '2026-10-09', '08:00:00', 0, false, false],
    'pending current interval' => ['pending', '2026-10-09', '10:00', 899, false, true],
    'pending future at hold boundary' => ['pending', '2026-10-10', '11:00', 900, false, true],
    'pending one second expired' => ['pending', '2026-10-10', '11:00', 901, false, false],
    'pending interval ended' => ['pending', '2026-10-09', '09:00', 0, false, false],
    'paid expired pending' => ['pending', '2026-10-10', '11:00', 3600, true, true],
    'paid past pending' => ['pending', '2026-10-08', '11:00', 86400, true, true],
    'cancelled even paid' => ['cancelled', '2026-10-10', '11:00', 0, true, false],
]);

test('compatible capacity edits inspect every protected party', function (int $guests, int $capacity, bool $allowed) {
    $this->travelTo('2026-10-09 03:00:00');
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    integrityReservation($table, ['guest_count' => 2]);
    integrityReservation($table, ['guest_count' => $guests, 'reservation_date' => '2026-10-10']);

    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['capacity' => $capacity]));
    if ($allowed) {
        $response->assertSessionHasNoErrors();
    } else {
        $response->assertSessionHasErrors('capacity');
    }
    $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => $allowed ? $capacity : 4]);
})->with(['compatible downsizing' => [2, 3, true], 'incompatible downsizing' => [4, 3, false], 'incompatible upsizing minimum' => [2, 6, false]]);

test('checked in visits block changes but allow unchanged capacity', function () {
    $this->travelTo('2026-10-09 03:00:00');
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $reservation = integrityReservation($table, ['checked_in_at' => now()]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['capacity' => 3]))->assertSessionHasErrors('capacity');
    $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['status' => 'maintenance']))->assertSessionHasErrors('status');
    $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['table_number' => 'Unchanged-capacity']))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => 4, 'status' => 'available', 'table_number' => 'Unchanged-capacity']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'checked_in_at' => now()->toDateTimeString(), 'status' => 'confirmed']);
});

test('stale table capacity or status cannot overwrite newer edits', function (string $field, mixed $value) {
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $payload = integrityTablePayload($table, ['table_number' => 'Stale', 'status' => 'maintenance']);
    $table->update([$field => $value]);
    $before = $table->refresh()->getAttributes();

    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.tables.update', $table), $payload)
        ->assertSessionHasErrors(['status' => 'This table has changed. Refresh the page before trying again.']);
    expect($table->refresh()->getAttributes())->toBe($before);
})->with(['capacity' => ['capacity', 3], 'status' => ['status', 'maintenance']]);

test('missing expected state cannot disable admin safeguards', function () {
    $table = Table::factory()->create(['capacity' => 4]);
    $target = Table::factory()->create(['capacity' => 4]);
    $reservation = integrityReservation($table);
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->patch(route('admin.tables.update', $table), ['table_number' => 'Missing', 'capacity' => 3, 'status' => 'maintenance'])
        ->assertSessionHasErrors(['expected_capacity', 'expected_status']);
    $this->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $target->id])
        ->assertSessionHasErrors(['expected_table_id', 'expected_status']);
    $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => 4, 'status' => 'available']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'table_id' => $table->id]);
});

test('stale reassignment and same table requests cannot overwrite a winning move', function (bool $sameTable) {
    $this->travelTo('2026-10-09 03:00:00');
    $source = Table::factory()->create(['capacity' => 4]);
    $target = Table::factory()->create(['capacity' => 4]);
    $reservation = integrityReservation($source);
    $payload = integrityAssignmentPayload($reservation, $sameTable ? $source : $target);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patch(route('admin.reservations.update-table', $reservation), integrityAssignmentPayload($reservation, $target))->assertSessionHas('status');

    $this->patch(route('admin.reservations.update-table', $reservation), $payload)
        ->assertSessionHas('error', 'This reservation has changed. Refresh the page before trying again.');
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'table_id' => $target->id]);
})->with([true, false]);

test('stale reservation status rejects reassignment', function () {
    $source = Table::factory()->create(['capacity' => 4]);
    $target = Table::factory()->create(['capacity' => 4]);
    $reservation = integrityReservation($source, ['status' => 'pending']);
    $payload = integrityAssignmentPayload($reservation, $target);
    $reservation->update(['status' => 'confirmed']);

    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.reservations.update-table', $reservation), $payload)->assertSessionHas('error');
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed', 'table_id' => $source->id]);
});

test('destination holds occupancy and maintenance are enforced', function (string $status, int $age, string $time, bool $maintenance, bool $allowed) {
    $this->travelTo('2026-10-09 03:00:00');
    $source = Table::factory()->create(['capacity' => 4]);
    $target = Table::factory()->create(['capacity' => 4, 'status' => $maintenance ? 'maintenance' : 'available']);
    $reservation = integrityReservation($source);
    integrityReservation($target, ['status' => $status, 'created_at' => now()->subSeconds($age), 'reservation_time' => $time]);

    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-table', $reservation), integrityAssignmentPayload($reservation, $target));
    if ($allowed) {
        $response->assertSessionHasNoErrors()->assertSessionHas('status');
    } else {
        $response->assertSessionHasErrors('table_id');
    }
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'table_id' => $allowed ? $target->id : $source->id]);
})->with([
    'confirmed overlap' => ['confirmed', 0, '11:00', false, false],
    'completed overlap' => ['completed', 0, '10:00', false, false],
    'completed adjacent' => ['completed', 0, '09:00', false, true],
    'live hold' => ['pending', 900, '11:00', false, false],
    'expired hold' => ['pending', 901, '11:00', false, true],
    'maintenance' => ['cancelled', 0, '11:00', true, false],
]);

test('the destination list excludes minimum unsuitable tables and submits expected state', function () {
    $source = Table::factory()->create(['capacity' => 4]);
    $suitable = Table::factory()->create(['capacity' => 3]);
    $unsuitable = Table::factory()->create(['capacity' => 6]);
    $reservation = integrityReservation($source, ['guest_count' => 1]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.reservations.show', $reservation))
        ->assertViewHas('availableTables', fn ($tables) => $tables->modelKeys() === [$suitable->id])
        ->assertSee('name="expected_table_id" value="'.$source->id.'"', false)
        ->assertSee('name="expected_status" value="confirmed"', false);
    $this->get(route('admin.tables.edit', $source))
        ->assertSee('name="expected_capacity" value="4"', false)
        ->assertSee('name="expected_status" value="available"', false);
});

test('table indicators include paid exceptions but exclude expired unpaid holds without renewing allocation', function () {
    $this->travelTo('2026-10-09 03:00:00');
    $table = Table::factory()->create(['capacity' => 4]);
    $paid = integrityReservation($table, ['status' => 'pending', 'created_at' => now()->subHour()]);
    Payment::factory()->forReservation($paid)->paid()->create();
    integrityReservation($table, ['status' => 'pending', 'created_at' => now()->subHour()]);
    expect(app(ReservationService::class)->isTableAvailable($table, $paid->reservation_date_time))->toBeTrue();

    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.tables.index'))
        ->assertViewHas('tables', fn ($tables) => $tables->first()->protected_count === 1)
        ->assertSee('Paid pending — manual resolution: 1');
});

test('management sees any paid pending payment even when another payment is unpaid', function () {
    $this->travelTo('2026-10-09 03:00:00');
    $table = Table::factory()->create(['capacity' => 4]);
    $reservation = integrityReservation($table, ['status' => 'pending', 'created_at' => now()->subHour()]);
    Payment::factory()->forReservation($reservation)->create();
    Payment::factory()->forReservation($reservation)->paid()->create();

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['status' => 'maintenance']))->assertSessionHasErrors('status');
    $this->get(route('admin.tables.index'))->assertSee('Paid pending — manual resolution: 1');
    $this->assertDatabaseHas('tables', ['id' => $table->id, 'status' => 'available']);
});

test('payment and management serialize safely in either winning order', function (bool $paymentFirst, string $edit) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $reservation = integrityReservation($table, ['reservation_type' => 'table_and_food', 'status' => 'pending', 'created_at' => now()->subHour()]);
    $reservation->reservationFoods()->create(['menu_item_id' => MenuItem::factory()->create()->id, 'item_name' => 'Integrity dish', 'unit_price' => 20, 'quantity' => 1]);
    $payment = Payment::factory()->forReservation($reservation)->create();
    $payload = integrityTablePayload($table, $edit === 'maintenance' ? ['status' => 'maintenance'] : ['capacity' => 6]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    if ($paymentFirst) {
        app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
        $this->patch(route('admin.tables.update', $table), $payload)->assertSessionHasErrors($edit === 'maintenance' ? 'status' : 'capacity');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => 4, 'status' => 'available']);
        Mail::assertSent(ReservationConfirmationMail::class, 1);
    } else {
        $this->patch(route('admin.tables.update', $table), $payload)->assertSessionHasNoErrors();
        app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending']);
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => $edit === 'maintenance' ? 4 : 6, 'status' => $edit === 'maintenance' ? 'maintenance' : 'available']);
        $this->get(route('admin.tables.index'))->assertSee('Paid pending — manual resolution: 1');
        Mail::assertNothingSent();
    }
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'amount' => 20]);
})->with([true, false])->with(['maintenance', 'capacity']);

test('level one confirmation and maintenance honor the winning operation', function (bool $confirmationFirst) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4]);
    $reservation = integrityReservation($table, ['status' => 'pending', 'created_at' => now()->subHour()]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    if ($confirmationFirst) {
        expect(app(ReservationService::class)->transition($reservation->id, 'confirmed', 'pending'))->toBeNull();
        $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['status' => 'maintenance']))->assertSessionHasErrors('status');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'status' => 'available']);
        Mail::assertSent(ReservationConfirmationMail::class, 1);
    } else {
        $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['status' => 'maintenance']))->assertSessionHasNoErrors();
        expect(app(ReservationService::class)->transition($reservation->id, 'confirmed', 'pending'))->not->toBeNull();
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending']);
        Mail::assertNothingSent();
    }
})->with([true, false]);

test('booking and reassignment cannot both allocate the same destination interval', function (bool $bookingFirst) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $source = Table::factory()->create(['capacity' => 4]);
    $destination = Table::factory()->create(['capacity' => 4]);
    $reservation = integrityReservation($source, ['reservation_date' => '2026-10-10']);
    $service = app(ReservationService::class);
    $booking = ['table_id' => $destination->id, 'guest_count' => 2, 'reservation_date' => '2026-10-10', 'reservation_time' => '11:00', 'customer_name' => 'New guest', 'customer_email' => 'new@example.com', 'customer_phone' => '0123456789'];

    if ($bookingFirst) {
        $service->createTableOnly($booking);
        expect(fn () => $service->reassign($reservation->id, $destination->id, $source->id, 'confirmed'))->toThrow(ValidationException::class);
    } else {
        expect($service->reassign($reservation->id, $destination->id, $source->id, 'confirmed'))->toBeNull();
        expect(fn () => $service->createTableOnly($booking))->toThrow(ValidationException::class);
    }
    expect(Reservation::where('table_id', $destination->id)->where('status', 'confirmed')->count())->toBe(1);
    Mail::assertSent(ReservationConfirmationMail::class, $bookingFirst ? 1 : 0);
})->with([true, false]);

test('capacity and allocation cannot invalidate a new party', function (bool $bookingFirst) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4]);
    $service = app(ReservationService::class);
    $booking = ['table_id' => $table->id, 'guest_count' => 2, 'reservation_date' => '2026-10-10', 'reservation_time' => '11:00', 'customer_name' => 'New guest', 'customer_email' => 'new@example.com', 'customer_phone' => '0123456789'];
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    if ($bookingFirst) {
        $service->createTableOnly($booking);
        $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['capacity' => 6]))->assertSessionHasErrors('capacity');
        $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => 4]);
    } else {
        $this->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['capacity' => 6]))->assertSessionHasNoErrors();
        expect(fn () => $service->createTableOnly($booking))->toThrow(ValidationException::class);
        $this->assertDatabaseCount('reservations', 0);
    }
    Mail::assertSent(ReservationConfirmationMail::class, $bookingFirst ? 1 : 0);
})->with([true, false]);

test('a check in after form loading is observed by the locked capacity guard', function () {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4]);
    $reservation = integrityReservation($table);
    $interleaved = false;
    DB::listen(function ($query) use (&$interleaved, $reservation) {
        if (! $interleaved && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from '.DB::connection()->getQueryGrammar()->wrapTable('tables'))) {
            $interleaved = true;
            app(ReservationService::class)->checkIn($reservation->reservation_number);
        }
    });

    $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.tables.update', $table), integrityTablePayload($table, ['capacity' => 3]))->assertSessionHasErrors('capacity');
    expect($interleaved)->toBeTrue();
    $this->assertDatabaseHas('tables', ['id' => $table->id, 'capacity' => 4]);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'checked_in_at' => now()->toDateTimeString()]);
    Mail::assertNothingSent();
});

test('opposite direction reassignment selects tables in ascending id order', function (bool $reverse) {
    $this->travelTo('2026-10-09 03:00:00');
    $first = Table::factory()->create(['capacity' => 4]);
    $second = Table::factory()->create(['capacity' => 4]);
    $source = $reverse ? $second : $first;
    $destination = $reverse ? $first : $second;
    $reservation = integrityReservation($source);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    expect(app(ReservationService::class)->reassign($reservation->id, $destination->id, $source->id, 'confirmed'))->toBeNull();
    $grammar = DB::connection()->getQueryGrammar();
    expect($queries[0])->toContain('from '.$grammar->wrapTable('tables'), 'order by '.$grammar->wrap('id').' asc', 'for update');
    expect($queries[1])->toContain('from '.$grammar->wrapTable('reservations'), 'for update');
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'table_id' => $destination->id]);
})->with([true, false]);

test('table mutations and reassignment remain admin only', function () {
    $source = Table::factory()->create(['capacity' => 4]);
    $destination = Table::factory()->create(['capacity' => 4]);
    $reservation = integrityReservation($source);

    $this->patch(route('admin.tables.update', $source), integrityTablePayload($source, ['status' => 'maintenance']))->assertRedirect(route('admin.login'));
    $this->patch(route('admin.reservations.update-table', $reservation), integrityAssignmentPayload($reservation, $destination))->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->create(['role' => 'customer']));
    $this->patch(route('admin.tables.update', $source), integrityTablePayload($source, ['status' => 'maintenance']))->assertForbidden();
    $this->patch(route('admin.reservations.update-table', $reservation), integrityAssignmentPayload($reservation, $destination))->assertForbidden();
    $this->assertDatabaseHas('tables', ['id' => $source->id, 'status' => 'available']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'table_id' => $source->id]);
});
