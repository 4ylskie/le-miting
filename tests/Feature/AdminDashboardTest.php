<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an administrator can view the dashboard summary', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee("Today's orders", false)
        ->assertSee("Today's revenue", false)
        ->assertSee('Pending reservations')
        ->assertSee('Available tables');
});

test('the dashboard reflects today activity', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Order::factory()->create(['status' => 'pending', 'created_at' => now()]);
    Payment::factory()->paid()->create(['amount' => 50.00]);

    $table = Table::factory()->create(['status' => 'available']);
    Reservation::factory()->confirmed()->create([
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '19:00',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('1')
        ->assertSee('RM 50.00')
        ->assertSee('Available tables');
});
