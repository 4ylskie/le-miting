<?php

use App\Mail\FeedbackInvitationMail;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('a signed feedback link for a completed order shows the feedback form', function () {
    config(['app.url' => 'http://localhost']);

    $order = Order::factory()->create(['status' => 'completed']);

    $this->get(URL::signedRoute('feedback.create', ['token' => 'order-'.$order->id]))
        ->assertOk()
        ->assertSee('order '.$order->order_number)
        ->assertSee('food_rating')
        ->assertSee('service_rating')
        ->assertSee('experience_rating');
});

test('a signed feedback link for a completed reservation shows the feedback form', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->create(['status' => 'completed']);

    $this->get(URL::signedRoute('feedback.create', ['token' => 'reservation-'.$reservation->id]))
        ->assertOk()
        ->assertSee('reservation '.$reservation->reservation_number)
        ->assertSee('food_rating');
});

test('a guest can submit feedback for a completed order', function () {
    config(['app.url' => 'http://localhost']);

    $order = Order::factory()->create(['status' => 'completed']);

    $this->post(URL::signedRoute('feedback.store', ['token' => 'order-'.$order->id]), [
        'food_rating' => 5,
        'service_rating' => 4,
        'experience_rating' => 4,
        'comment' => 'Lovely meal',
    ])
        ->assertOk()
        ->assertSee('Thank You!')
        ->assertSee('Google');

    $this->assertDatabaseHas('feedback', [
        'order_id' => $order->id,
        'reservation_id' => null,
        'food_rating' => 5,
        'service_rating' => 4,
        'experience_rating' => 4,
        'comment' => 'Lovely meal',
    ]);
});

test('a guest can submit feedback for a completed reservation', function () {
    config(['app.url' => 'http://localhost']);

    $reservation = Reservation::factory()->create(['status' => 'completed']);

    $this->post(URL::signedRoute('feedback.store', ['token' => 'reservation-'.$reservation->id]), [
        'food_rating' => 4,
        'service_rating' => 5,
        'experience_rating' => 5,
        'comment' => null,
    ])->assertOk();

    $this->assertDatabaseHas('feedback', [
        'order_id' => null,
        'reservation_id' => $reservation->id,
        'food_rating' => 4,
    ]);
});

test('feedback can only be given for a completed transaction', function () {
    config(['app.url' => 'http://localhost']);

    $order = Order::factory()->create(['status' => 'pending']);

    $this->get(URL::signedRoute('feedback.create', ['token' => 'order-'.$order->id]))
        ->assertNotFound();

    $this->post(URL::signedRoute('feedback.store', ['token' => 'order-'.$order->id]), [
        'food_rating' => 5,
        'service_rating' => 5,
        'experience_rating' => 5,
    ])->assertNotFound();

    $this->assertDatabaseMissing('feedback', ['order_id' => $order->id]);
});

test('an unsigned or tampered feedback link is rejected', function () {
    $order = Order::factory()->create(['status' => 'completed']);

    $this->get('/feedback/order-'.$order->id)
        ->assertForbidden()
        ->assertSee('This link is no longer valid.');

    $this->get(route('feedback.create', ['token' => 'order-'.$order->id]).'?signature=invalid')
        ->assertForbidden()
        ->assertSee('This link is no longer valid.');
});

test('feedback cannot be submitted twice for the same transaction', function () {
    config(['app.url' => 'http://localhost']);

    $order = Order::factory()->create(['status' => 'completed']);
    Feedback::factory()->create(['order_id' => $order->id, 'reservation_id' => null]);

    $this->get(URL::signedRoute('feedback.create', ['token' => 'order-'.$order->id]))
        ->assertOk()
        ->assertSee('Thank You!')
        ->assertDontSee('food_rating');

    $this->post(URL::signedRoute('feedback.store', ['token' => 'order-'.$order->id]), [
        'food_rating' => 1,
        'service_rating' => 1,
        'experience_rating' => 1,
    ])->assertOk();

    expect(Feedback::where('order_id', $order->id)->count())->toBe(1);
});

test('feedback ratings are validated', function () {
    config(['app.url' => 'http://localhost']);

    $order = Order::factory()->create(['status' => 'completed']);

    $this->post(URL::signedRoute('feedback.store', ['token' => 'order-'.$order->id]), [
        'food_rating' => 9,
        'service_rating' => 0,
        'experience_rating' => 'excellent',
    ])->assertSessionHasErrors(['food_rating', 'service_rating', 'experience_rating']);
});

test('completing an order sends a feedback invitation', function () {
    config(['app.url' => 'http://localhost']);
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create([
        'status' => 'ready',
        'customer_email' => 'guest@example.com',
        'customer_name' => 'Jane',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'completed'])
        ->assertRedirect();

    Mail::assertSent(FeedbackInvitationMail::class, function (FeedbackInvitationMail $mail) use ($order) {
        return $mail->to[0]['address'] === 'guest@example.com'
            && str_contains($mail->feedbackUrl, '/feedback/order-'.$order->id)
            && $mail->transactionLabel === 'order '.$order->order_number;
    });
});

test('completing a reservation sends a feedback invitation', function () {
    config(['app.url' => 'http://localhost']);
    Mail::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $reservation = Reservation::factory()->create([
        'status' => 'confirmed',
        'customer_email' => 'guest@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed'])
        ->assertRedirect();

    Mail::assertSent(FeedbackInvitationMail::class, function (FeedbackInvitationMail $mail) use ($reservation) {
        return $mail->to[0]['address'] === 'guest@example.com'
            && str_contains($mail->feedbackUrl, '/feedback/reservation-'.$reservation->id)
            && $mail->transactionLabel === 'reservation '.$reservation->reservation_number;
    });
});
