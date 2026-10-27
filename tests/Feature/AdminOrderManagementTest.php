<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('an administrator can list and filter orders', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $order = Order::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['status' => 'pending']))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order->customer_name);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee($order->order_number);
});

test('an administrator can move an order forward through the workflow', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create(['status' => 'pending']);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => $order->total]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'confirmed', 'expected_status' => $order->status])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($order->refresh()->status)->toBe('confirmed');

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'preparing', 'expected_status' => $order->status])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($order->refresh()->status)->toBe('preparing');

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'ready', 'expected_status' => $order->status])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($order->refresh()->status)->toBe('ready');

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'completed', 'expected_status' => $order->status])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe('completed');
});

test('an invalid order status transition is rejected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'completed', 'expected_status' => $order->status])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe('pending');
});

test('a completed order cannot be moved again', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create(['status' => 'completed']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'ready', 'expected_status' => $order->status])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe('completed');
});
