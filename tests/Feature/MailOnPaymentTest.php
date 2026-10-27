<?php

use App\Mail\OrderConfirmationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\Payments\MockPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function mockCallbackPayload(Payment $payment, string $status): array
{
    return app(MockPaymentService::class)->callbackPayload($payment, $status === 'paid' ? 'success' : 'failure');
}

test('a verified order payment confirms the order and emails the customer', function () {
    Mail::fake();
    config()->set('payment.driver', 'mock');

    $order = Order::factory()->create(['customer_email' => 'customer@example.com', 'status' => 'pending', 'total' => 42.5]);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'pending', 'amount' => 42.5]);

    $payload = mockCallbackPayload($payment, 'paid');
    $amount = $payload['amount'];

    $response = $this->post(route('payment.callback'), $payload);

    $response->assertRedirect();
    $this->get($response->headers->get('Location'))->assertOk();

    $this->post(route('payment.callback'), $payload)->assertRedirect();

    Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
        return $mail->hasTo('customer@example.com');
    });
    Mail::assertSent(OrderConfirmationMail::class, 1);

    expect($order->refresh()->status)->toBe('confirmed');
    expect($payment->refresh()->raw_response)->toBe([
        'reference' => $payment->provider_reference,
        'amount' => $amount,
        'status' => 'paid',
        'currency' => 'MYR',
    ]);
    expect($payment->raw_response)->not->toHaveKey('signature');
});

test('a verified reservation payment confirms the reservation and emails the customer', function () {
    Mail::fake();
    config()->set('payment.driver', 'mock');

    $reservation = Reservation::factory()->withFood()->create([
        'status' => 'pending',
        'customer_email' => 'res@example.com',
        'table_id' => Table::factory()->create(['capacity' => 4])->id,
        'guest_count' => 2,
    ]);
    $menuItem = MenuItem::factory()->create();
    $reservation->reservationFoods()->create([
        'menu_item_id' => $menuItem->id,
        'item_name' => 'Satay Skewers',
        'unit_price' => 10.00,
        'quantity' => 1,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->create(['amount' => 10.0]);

    $response = $this->post(route('payment.callback'), mockCallbackPayload($payment, 'paid'));

    $response->assertRedirect();
    $this->get($response->headers->get('Location'))->assertOk();

    Mail::assertSent(ReservationConfirmationMail::class, function (ReservationConfirmationMail $mail) {
        return $mail->hasTo('res@example.com');
    });

    expect($reservation->refresh()->status)->toBe('confirmed');
});

test('a valid signature with the wrong amount fails without confirming the order', function () {
    Mail::fake();
    config()->set('payment.driver', 'mock');

    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'pending', 'amount' => 42.5]);
    $wrongAmount = '41.50';

    $callbackPayment = $payment->replicate();
    $callbackPayment->provider_reference = $payment->provider_reference;
    $callbackPayment->amount = $wrongAmount;

    $this->post(route('payment.callback'), mockCallbackPayload($callbackPayment, 'paid'))
        ->assertRedirect(route('payment.failed', $payment));

    Mail::assertNothingSent();

    expect($payment->refresh()->status)->toBe('failed');
    expect($order->refresh()->status)->toBe('pending');
});

test('an invalid callback signature cannot change a payment or confirm its order', function () {
    Mail::fake();
    config()->set('payment.driver', 'mock');

    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'pending', 'amount' => 42.5]);
    $amount = number_format((float) $payment->amount, 2, '.', '');

    $this->post(route('payment.callback'), [
        'reference' => $payment->provider_reference,
        'amount' => $amount,
        'status' => 'paid',
        'currency' => 'MYR',
        'signature' => 'invalid-signature',
    ])->assertUnprocessable();

    Mail::assertNothingSent();

    expect($payment->refresh()->status)->toBe('pending');
    expect($order->refresh()->status)->toBe('pending');
});

test('an unknown payment reference cannot create or update a payment', function () {
    config()->set('payment.driver', 'mock');
    $unknownPayment = Payment::factory()->make([
        'provider_reference' => 'PAY-UNKNOWN',
        'amount' => 42.50,
    ]);

    $this->post(route('payment.callback'), mockCallbackPayload($unknownPayment, 'paid'))->assertUnprocessable();

    $this->assertDatabaseMissing('payments', ['provider_reference' => 'PAY-UNKNOWN']);
});
