<?php

use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function reservationAt(Table $table, string $time = '19:00', string $status = 'pending'): Reservation
{
    return Reservation::factory()->create([
        'table_id' => $table->id,
        'guest_count' => 1,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => $time,
        'status' => $status,
    ]);
}

test('an administrator can search reservations by reservation number', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available']);
    $reservation = reservationAt($table);

    $this->actingAs($admin)
        ->get(route('admin.reservations.index', ['search' => $reservation->reservation_number]))
        ->assertOk()
        ->assertSee($reservation->reservation_number)
        ->assertSee($reservation->customer_name);
});

test('an administrator can view reservation details', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available']);
    $reservation = reservationAt($table);

    $this->actingAs($admin)
        ->get(route('admin.reservations.show', $reservation))
        ->assertOk()
        ->assertSee($reservation->reservation_number)
        ->assertSee('Table '.$table->table_number);
});

test('an administrator can confirm a pending reservation', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $reservation = reservationAt($table);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'confirmed', 'expected_status' => $reservation->status])
        ->assertRedirect();

    expect($reservation->refresh()->status)->toBe('confirmed');
});

test('an administrator can look up and check in a confirmed reservation by reservation number', function () {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $reservation = Reservation::factory()->confirmed()->create([
        'table_id' => $table->id, 'guest_count' => 2,
        'reservation_date' => '2026-10-09', 'reservation_time' => '11:00',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number])
        ->assertRedirect(route('admin.reservations.show', $reservation));

    expect($reservation->refresh()->status)->toBe('confirmed');
    expect($reservation->checked_in_at->equalTo(now()))->toBeTrue();
    Mail::assertNothingSent();
});

test('an administrator cannot check in a reservation outside the confirmed state', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available']);
    $reservation = reservationAt($table, status: $status);

    $this->actingAs($admin)
        ->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number])
        ->assertSessionHasErrors('reservation_number');

    expect($reservation->refresh()->status)->toBe($status);
})->with(['pending', 'cancelled', 'completed']);

test('an invalid reservation status transition is rejected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available']);
    $reservation = reservationAt($table);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($reservation->refresh()->status)->toBe('pending');
});

test('an administrator can reassign an active reservation to an available table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $current = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $target = Table::factory()->create(['status' => 'available', 'capacity' => 6]);

    $reservation = reservationAt($current);
    $reservation->update(['guest_count' => 3]);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $target->id, 'expected_table_id' => $reservation->table_id, 'expected_status' => $reservation->status])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($reservation->refresh()->table_id)->toBe($target->id);
});

test('a reservation cannot be reassigned to a table that is too small', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $current = Table::factory()->create(['status' => 'available', 'capacity' => 8]);
    $small = Table::factory()->create(['status' => 'available', 'capacity' => 2]);

    $reservation = Reservation::factory()->create([
        'table_id' => $current->id,
        'guest_count' => 4,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '19:00',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $small->id, 'expected_table_id' => $reservation->table_id, 'expected_status' => $reservation->status])
        ->assertSessionHasErrors('table_id');

    expect($reservation->refresh()->table_id)->toBe($current->id);
});

test('a reservation cannot be reassigned to a table already reserved at that time', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $occupied = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $current = Table::factory()->create(['status' => 'available', 'capacity' => 4]);

    reservationAt($occupied, '19:00', 'confirmed');
    $reservation = reservationAt($current, '19:00');

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $occupied->id, 'expected_table_id' => $reservation->table_id, 'expected_status' => $reservation->status])
        ->assertSessionHasErrors('table_id')
        ->assertSessionHasErrors(['table_id' => 'That table is already reserved for this time. Choose another table.']);

    expect($reservation->refresh()->table_id)->toBe($current->id);
});

test('reassigning a reservation to its current table is a no-op', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $reservation = reservationAt($table);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $table->id, 'expected_table_id' => $reservation->table_id, 'expected_status' => $reservation->status])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($reservation->refresh()->table_id)->toBe($table->id);
});

test('completed reservations cannot be reassigned', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $current = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $target = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $reservation = reservationAt($current, '19:00', 'completed');

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $target->id, 'expected_table_id' => $reservation->table_id, 'expected_status' => $reservation->status])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($reservation->refresh()->table_id)->toBe($current->id);
});
