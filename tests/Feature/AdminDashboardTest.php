<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Carbon\CarbonImmutable;
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

test('an administrator can fetch the current dashboard summary metrics', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00', 'UTC'));
    $admin = User::factory()->create(['role' => 'admin']);

    $todayOrder = Order::factory()->create([
        'status' => 'confirmed',
        'created_at' => CarbonImmutable::parse('2026-10-19 15:00:00', 'UTC'),
    ]);
    $olderOrder = Order::factory()->create([
        'status' => 'confirmed',
        'created_at' => CarbonImmutable::parse('2026-10-18 15:00:00', 'UTC'),
    ]);
    Order::factory()->count(2)->create([
        'status' => 'pending',
        'created_at' => CarbonImmutable::parse('2026-10-18 15:00:00', 'UTC'),
    ]);

    Payment::factory()->paid()->create([
        'order_id' => $todayOrder->id,
        'reservation_id' => null,
        'amount' => 125.50,
        'paid_at' => CarbonImmutable::parse('2026-10-19 15:30:00', 'UTC'),
    ]);
    Payment::factory()->paid()->create([
        'order_id' => $olderOrder->id,
        'reservation_id' => null,
        'amount' => 400.00,
        'paid_at' => CarbonImmutable::parse('2026-10-18 15:30:00', 'UTC'),
    ]);

    $availableTable = Table::factory()->create(['status' => 'available']);
    Table::factory()->create(['status' => 'available']);
    $occupiedTable = Table::factory()->create(['status' => 'occupied']);

    Reservation::factory()->create([
        'table_id' => $availableTable->id,
        'reservation_date' => '2026-10-19',
        'reservation_time' => '21:00',
        'status' => 'pending',
    ]);
    Reservation::factory()->confirmed()->create([
        'table_id' => $occupiedTable->id,
        'reservation_date' => '2026-10-20',
        'reservation_time' => '13:00',
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.dashboard.summary'))
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'ordersToday' => 1,
            'reservationsToday' => 1,
            'revenueToday' => 125.5,
            'pendingOrders' => 2,
            'pendingReservations' => 1,
            'availableTables' => 2,
        ]);
});

test('a guest receives an unauthorized response from the dashboard summary endpoint', function () {
    $this->getJson(route('admin.dashboard.summary'))
        ->assertUnauthorized();
});

test('an authenticated non-admin is forbidden from the dashboard summary endpoint', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->getJson(route('admin.dashboard.summary'))
        ->assertForbidden();
});
