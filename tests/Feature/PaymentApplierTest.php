<?php

use App\Events\OrderPaymentVerified;
use App\Events\ReservationPaymentVerified;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\Payments\PaymentApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('a first verified payment confirms its pending order, stores callback audit data, and dispatches once', function () {
    Event::fake([OrderPaymentVerified::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => $order->total, 'status' => 'pending']);

    app(PaymentApplier::class)->applyVerifiedPayment([
        'reference' => $payment->provider_reference,
        'amount' => $payment->amount,
        'currency' => 'MYR',
        'raw_response' => [
            'reference' => $payment->provider_reference,
            'status' => 'paid',
            'amount' => (string) $payment->amount,
            'currency' => 'MYR',
        ],
    ]);

    Event::assertDispatchedTimes(OrderPaymentVerified::class, 1);

    expect($order->refresh()->status)->toBe('confirmed');
    expect($payment->refresh()->status)->toBe('paid');
    expect($payment->paid_at)->not->toBeNull();
    expect($payment->raw_response)->toBe([
        'reference' => $payment->provider_reference,
        'status' => 'paid',
        'amount' => (string) $payment->amount,
        'currency' => 'MYR',
    ]);
});

test('a duplicate verified order payment is not reapplied or redispatched', function () {
    Event::fake([OrderPaymentVerified::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => $order->total, 'status' => 'pending']);
    $applier = app(PaymentApplier::class);

    $applier->applyVerifiedPayment(['reference' => $payment->provider_reference]);
    $firstPaidAt = $payment->refresh()->paid_at;
    $applier->applyVerifiedPayment(['reference' => $payment->provider_reference]);

    Event::assertDispatchedTimes(OrderPaymentVerified::class, 1);

    expect($order->refresh()->status)->toBe('confirmed');
    expect($payment->refresh()->paid_at->equalTo($firstPaidAt))->toBeTrue();
});

test('a duplicate verified callback cannot regress a progressed order', function (string $status) {
    Event::fake([OrderPaymentVerified::class]);

    $order = Order::factory()->create(['status' => $status]);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'paid', 'paid_at' => now()->subMinute()]);

    app(PaymentApplier::class)->applyVerifiedPayment(['reference' => $payment->provider_reference]);

    Event::assertNotDispatched(OrderPaymentVerified::class);

    expect($order->refresh()->status)->toBe($status);
})->with(['preparing', 'ready', 'completed', 'cancelled']);

test('a first verified payment confirms its pending reservation and dispatches once', function () {
    Event::fake([ReservationPaymentVerified::class]);

    $reservation = Reservation::factory()->withFood()->create([
        'status' => 'pending',
        'table_id' => Table::factory()->create(['capacity' => 4])->id,
        'guest_count' => 2,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->create(['status' => 'pending']);
    $applier = app(PaymentApplier::class);

    $applier->applyVerifiedPayment(['reference' => $payment->provider_reference]);
    $applier->applyVerifiedPayment(['reference' => $payment->provider_reference]);

    Event::assertDispatchedTimes(ReservationPaymentVerified::class, 1);

    expect($reservation->refresh()->status)->toBe('confirmed');
    expect($payment->refresh()->status)->toBe('paid');
});

test('a duplicate verified callback cannot regress a terminal reservation', function (string $status) {
    Event::fake([ReservationPaymentVerified::class]);

    $reservation = Reservation::factory()->create(['status' => $status]);
    $payment = Payment::factory()->forReservation($reservation)->create(['status' => 'paid', 'paid_at' => now()->subMinute()]);

    app(PaymentApplier::class)->applyVerifiedPayment(['reference' => $payment->provider_reference]);

    Event::assertNotDispatched(ReservationPaymentVerified::class);

    expect($reservation->refresh()->status)->toBe($status);
})->with(['completed', 'cancelled']);

test('a failed payment cannot later confirm its order', function () {
    Event::fake([OrderPaymentVerified::class]);

    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => $order->total, 'status' => 'pending']);
    $applier = app(PaymentApplier::class);

    $applier->applyFailedPayment(['reference' => $payment->provider_reference]);
    $applier->applyVerifiedPayment(['reference' => $payment->provider_reference]);

    Event::assertNotDispatched(OrderPaymentVerified::class);

    expect($payment->refresh()->status)->toBe('failed');
    expect($order->refresh()->status)->toBe('pending');
});

test('a payment cannot belong to both an order and reservation', function () {
    $order = Order::factory()->create();
    $reservation = Reservation::factory()->create();

    expect(fn () => Payment::factory()->create([
        'order_id' => $order->id,
        'reservation_id' => $reservation->id,
    ]))->toThrow(ValidationException::class);
});

test('a payment cannot belong to neither an order nor reservation', function () {
    expect(fn () => Payment::factory()->create([
        'order_id' => null,
        'reservation_id' => null,
    ]))->toThrow(ValidationException::class);
});
