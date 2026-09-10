<?php

use App\Events\OrderPaymentVerified;
use App\Events\ReservationPaymentVerified;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Payments\PaymentApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('applyVerifiedPayment is idempotent and dispatches the order event once', function () {
    Event::fake([OrderPaymentVerified::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'pending']);

    $applier = app(PaymentApplier::class);

    $applier->applyVerifiedPayment(['reference' => $payment->provider_reference, 'amount' => $payment->amount, 'currency' => 'MYR']);
    $applier->applyVerifiedPayment(['reference' => $payment->provider_reference, 'amount' => $payment->amount, 'currency' => 'MYR']);

    Event::assertDispatchedTimes(OrderPaymentVerified::class, 1);

    expect($order->refresh()->status)->toBe('confirmed');
    expect($payment->refresh()->status)->toBe('paid');
    expect($payment->paid_at)->not->toBeNull();
});

test('applyVerifiedPayment confirms a reservation and dispatches its event', function () {
    Event::fake([ReservationPaymentVerified::class]);

    $reservation = Reservation::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create(['reservation_id' => $reservation->id, 'status' => 'pending']);

    app(PaymentApplier::class)->applyVerifiedPayment(['reference' => $payment->provider_reference, 'amount' => $payment->amount, 'currency' => 'MYR']);

    Event::assertDispatched(ReservationPaymentVerified::class, fn ($event) => $event->reservation->is($reservation));

    expect($reservation->refresh()->status)->toBe('confirmed');
});

test('applyFailedPayment marks the payment as failed', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    app(PaymentApplier::class)->applyFailedPayment(['reference' => $payment->provider_reference]);

    expect($payment->refresh()->status)->toBe('failed');
});
