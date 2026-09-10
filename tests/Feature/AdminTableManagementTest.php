<?php

use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an administrator can create a table', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.tables.store'), [
            'table_number' => 'B12',
            'capacity' => 6,
            'status' => 'available',
        ])
        ->assertRedirect(route('admin.tables.index'));

    $this->assertDatabaseHas('tables', ['table_number' => 'B12', 'capacity' => 6, 'status' => 'available']);
});

test('a duplicate table number is rejected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['table_number' => 'A1']);

    $this->actingAs($admin)
        ->post(route('admin.tables.store'), [
            'table_number' => 'A1',
            'capacity' => 4,
            'status' => 'available',
        ])
        ->assertSessionHasErrors('table_number');

    expect(Table::where('table_number', 'A1')->count())->toBe(1);
});

test('an administrator can update a table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['table_number' => 'A2', 'capacity' => 2]);

    $this->actingAs($admin)
        ->patch(route('admin.tables.update', $table), [
            'table_number' => 'A2',
            'capacity' => 4,
            'status' => 'available',
        ])
        ->assertRedirect(route('admin.tables.index'));

    expect($table->refresh()->capacity)->toBe(4);
});

test('a table with an upcoming confirmed reservation cannot go into maintenance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['table_number' => 'A3', 'status' => 'available']);

    Reservation::factory()->confirmed()->create([
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '19:00',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.tables.update', $table), [
            'table_number' => 'A3',
            'capacity' => $table->capacity,
            'status' => 'maintenance',
        ])
        ->assertSessionHasErrors('status');

    expect($table->refresh()->status)->toBe('available');
});

test('a table with no upcoming reservations can go into maintenance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['table_number' => 'A4', 'status' => 'available']);

    $this->actingAs($admin)
        ->patch(route('admin.tables.update', $table), [
            'table_number' => 'A4',
            'capacity' => $table->capacity,
            'status' => 'maintenance',
        ])
        ->assertRedirect(route('admin.tables.index'));

    expect($table->refresh()->status)->toBe('maintenance');
});

test('the tables index shows upcoming reservation counts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create(['table_number' => 'A5', 'status' => 'available']);

    Reservation::factory()->confirmed()->create([
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '19:00',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.tables.index'))
        ->assertOk()
        ->assertSee('A5')
        ->assertSee('>1<', false);
});
