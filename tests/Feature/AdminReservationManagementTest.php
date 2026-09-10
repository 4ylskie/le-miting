<?php

use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available']);
    $reservation = reservationAt($table);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'confirmed'])
        ->assertRedirect();

    expect($reservation->refresh()->status)->toBe('confirmed');
});

test('an invalid reservation status transition is rejected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available']);
    $reservation = reservationAt($table);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($reservation->refresh()->status)->toBe('pending');
});

test('an administrator can reassign an active reservation to an available table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $current = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $target = Table::factory()->create(['status' => 'available', 'capacity' => 6]);

    $reservation = reservationAt($current);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $target->id])
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
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $small->id])
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
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $occupied->id])
        ->assertSessionHasErrors('table_id')
        ->assertSessionHasErrors(['table_id' => 'That table is already reserved for this time. Choose another table.']);

    expect($reservation->refresh()->table_id)->toBe($current->id);
});

test('reassigning a reservation to its current table is a no-op', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    $reservation = reservationAt($table);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $table->id])
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
        ->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $target->id])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($reservation->refresh()->table_id)->toBe($current->id);
});
