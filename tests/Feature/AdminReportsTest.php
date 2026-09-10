<?php

use App\Models\Feedback;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('an administrator can view the reports page listing all six reports', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Weekly Sales')
        ->assertSee('Monthly Sales')
        ->assertSee('Weekly Reservations')
        ->assertSee('Monthly Reservations')
        ->assertSee('Weekly Feedback')
        ->assertSee('Monthly Feedback');
});

test('the sales reports download as PDFs', function () {
    $order = Order::factory()->create(['status' => 'completed', 'created_at' => now()->subDay()]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 45.50]);
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 3]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.sales-weekly'))
        ->assertOk()
        ->assertDownload('weekly-sales-report.pdf');

    $this->actingAs($this->admin)
        ->get(route('admin.reports.sales-monthly'))
        ->assertOk()
        ->assertDownload('monthly-sales-report.pdf');
});

test('the reservation reports download as PDFs', function () {
    Reservation::factory()->create(['status' => 'completed', 'created_at' => now()->subDay()]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.reservations-weekly'))
        ->assertOk()
        ->assertDownload('weekly-reservations-report.pdf');

    $this->actingAs($this->admin)
        ->get(route('admin.reports.reservations-monthly'))
        ->assertOk()
        ->assertDownload('monthly-reservations-report.pdf');
});

test('the feedback reports download as PDFs', function () {
    Feedback::factory()->create([
        'food_rating' => 5,
        'service_rating' => 4,
        'experience_rating' => 4,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.feedback-weekly'))
        ->assertOk()
        ->assertDownload('weekly-feedback-report.pdf');

    $this->actingAs($this->admin)
        ->get(route('admin.reports.feedback-monthly'))
        ->assertOk()
        ->assertDownload('monthly-feedback-report.pdf');
});
