<?php

use App\Mail\GoogleReviewInvitationMail;
use App\Mail\ReservationCancellationMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('completing an order sends an optional Google Review invitation to the configured destination', function () {
    $reviewUrl = 'https://www.google.com/maps?cid=le-miting-review';
    config(['restaurant.google_review_url' => $reviewUrl]);
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create([
        'status' => 'ready',
        'customer_email' => 'guest@example.com',
        'customer_name' => 'Jane',
    ]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => $order->total]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'completed', 'expected_status' => $order->status])
        ->assertRedirect();

    Mail::assertSent(GoogleReviewInvitationMail::class, function (GoogleReviewInvitationMail $mail) use ($order, $reviewUrl) {
        return $mail->to[0]['address'] === 'guest@example.com'
            && $mail->transactionLabel === 'order '.$order->order_number
            && $mail->googleReviewUrl === $reviewUrl;
    });

    (new GoogleReviewInvitationMail('Jane', 'order '.$order->order_number, $reviewUrl))
        ->assertSeeInHtml($reviewUrl)
        ->assertSeeInHtml('Leaving a review is optional.');
});

test('completing a reservation sends a Google Review invitation', function () {
    $this->travelTo('2026-10-09 04:00:00');
    $reviewUrl = 'https://www.google.com/maps?cid=le-miting-review';
    config(['restaurant.google_review_url' => $reviewUrl]);
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $reservation = Reservation::factory()->create([
        'status' => 'confirmed',
        'checked_in_at' => now()->subHour(),
        'reservation_date' => '2026-10-09',
        'reservation_time' => '11:00',
        'table_id' => Table::factory()->create(['capacity' => 4])->id,
        'guest_count' => 2,
        'customer_email' => 'guest@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])
        ->assertRedirect();

    Mail::assertSent(GoogleReviewInvitationMail::class, function (GoogleReviewInvitationMail $mail) use ($reservation, $reviewUrl) {
        return $mail->to[0]['address'] === 'guest@example.com'
            && $mail->transactionLabel === 'reservation '.$reservation->reservation_number
            && $mail->googleReviewUrl === $reviewUrl;
    });
});

test('incomplete and repeated completed transitions do not send a Google Review invitation', function () {
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $pendingOrder = Order::factory()->create(['status' => 'pending']);
    $pendingReservation = Reservation::factory()->create(['status' => 'pending']);
    $completedOrder = Order::factory()->create(['status' => 'completed']);

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $pendingOrder), ['status' => 'cancelled', 'expected_status' => $pendingOrder->status])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-status', $pendingReservation), ['status' => 'cancelled', 'expected_status' => $pendingReservation->status])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $completedOrder), ['status' => 'completed', 'expected_status' => $completedOrder->status])
        ->assertRedirect()
        ->assertSessionHas('error');

    Mail::assertNotSent(GoogleReviewInvitationMail::class);
    Mail::assertSent(ReservationCancellationMail::class, 1);
});

test('internal feedback routes, reports, and storage do not exist', function () {
    expect(Route::has('feedback.create'))->toBeFalse()
        ->and(Route::has('feedback.store'))->toBeFalse()
        ->and(Route::has('admin.feedback.index'))->toBeFalse()
        ->and(Route::has('admin.reports.feedback-weekly'))->toBeFalse()
        ->and(Route::has('admin.reports.feedback-monthly'))->toBeFalse()
        ->and(Schema::hasTable('feedback'))->toBeFalse();

    $this->get('/feedback/order-1')->assertNotFound();
    $this->get('/admin/feedback')->assertNotFound();
    $this->get('/admin/reports/feedback/weekly')->assertNotFound();
});
