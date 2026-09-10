<?php

use App\Mail\OrderConfirmationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function callbackSignature(Payment $payment, string $amount, string $status): string
{
    $secret = (string) config('payment.fiuu.secret_key');

    return hash_hmac('sha256', $secret.$payment->provider_reference.$amount.$status.'MYR', $secret);
}

test('a verified order payment confirms the order and emails the customer', function () {
    Mail::fake();
    config()->set('payment.driver', 'mock');
    config()->set('payment.fiuu.secret_key', 'test-secret');

    $order = Order::factory()->create(['customer_email' => 'customer@example.com', 'status' => 'pending']);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'pending', 'amount' => 42.5]);

    $amount = number_format((float) $payment->amount, 2, '.', '');

    $this->post(route('payment.callback'), [
        'reference' => $payment->provider_reference,
        'amount' => $amount,
        'status' => 'paid',
        'currency' => 'MYR',
        'signature' => callbackSignature($payment, $amount, 'paid'),
    ])->assertRedirect(route('orders.confirmation', $order));

    Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
        return $mail->hasTo('customer@example.com');
    });

    expect($order->refresh()->status)->toBe('confirmed');
});

test('a verified reservation payment confirms the reservation and emails the customer', function () {
    Mail::fake();
    config()->set('payment.driver', 'mock');
    config()->set('payment.fiuu.secret_key', 'test-secret');

    $reservation = Reservation::factory()->withFood()->create([
        'status' => 'pending',
        'customer_email' => 'res@example.com',
    ]);
    $menuItem = MenuItem::factory()->create();
    $reservation->reservationFoods()->create([
        'menu_item_id' => $menuItem->id,
        'item_name' => 'Satay Skewers',
        'unit_price' => 10.00,
        'quantity' => 1,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->create(['amount' => 10.0]);

    $amount = number_format((float) $payment->amount, 2, '.', '');

    $this->post(route('payment.callback'), [
        'reference' => $payment->provider_reference,
        'amount' => $amount,
        'status' => 'paid',
        'currency' => 'MYR',
        'signature' => callbackSignature($payment, $amount, 'paid'),
    ])->assertRedirect(route('reservation.confirmation', $reservation));

    Mail::assertSent(ReservationConfirmationMail::class, function (ReservationConfirmationMail $mail) {
        return $mail->hasTo('res@example.com');
    });

    expect($reservation->refresh()->status)->toBe('confirmed');
});
