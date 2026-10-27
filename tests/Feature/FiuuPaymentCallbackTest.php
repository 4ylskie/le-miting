<?php

use App\Events\OrderPaymentVerified;
use App\Events\ReservationPaymentVerified;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('callback replay across Malaysian midnight preserves the original UTC payment instant', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:59:59', 'UTC'));
    $order = Order::factory()->create(['status' => 'pending', 'total' => 25]);
    $payment = Payment::factory()->for($order)->create(['amount' => '25.00']);
    Event::fake([OrderPaymentVerified::class]);
    $payload = signedAdapterCallback($payment->provider_reference);

    $this->postJson(route('payment.callback'), $payload)->assertOk();
    $first = $payment->fresh()->getAttributes();
    $this->travelTo(CarbonImmutable::parse('2026-10-09 16:00:01', 'UTC'));
    $this->postJson(route('payment.callback'), $payload)->assertOk();

    expect($payment->fresh()->getAttributes())->toBe($first);
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'paid_at' => '2026-10-09 15:59:59', 'status' => 'paid']);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    Event::assertDispatchedTimes(OrderPaymentVerified::class, 1);
});

beforeEach(function () {
    config(['payment.driver' => 'fiuu', 'payment.fiuu.secret_key' => 'callback-test-secret']);
});

/**
 * Synthetic fixtures for the existing adapter, not provider-certified FIUU payloads.
 *
 * @return array<string, string>
 */
function signedAdapterCallback(string $reference, string $status = 'paid', string $amount = '25.00'): array
{
    return [
        'bill_code' => $reference,
        'bill_status' => $status,
        'bill_amount' => $amount,
        'signature' => md5('callback-test-secret'.$reference.$amount.$status),
    ];
}

test('signed adapter callbacks confirm an order using either supported alias set', function (bool $shortAliases) {
    $order = Order::factory()->create(['status' => 'pending', 'total' => 25]);
    $payment = Payment::factory()->for($order)->create(['amount' => '25.00']);
    Event::fake([OrderPaymentVerified::class]);
    $payload = signedAdapterCallback($payment->provider_reference);
    if ($shortAliases) {
        $payload = ['reference' => $payload['bill_code'], 'status' => $payload['bill_status'], 'amount' => $payload['bill_amount'], 'signature' => $payload['signature']];
    }

    $this->postJson(route('payment.callback'), $payload)->assertOk()->assertExactJson(['status' => 'OK']);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'amount' => '25.00']);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    Event::assertDispatchedTimes(OrderPaymentVerified::class, 1);
    unset($payload['signature']);
    expect($payment->refresh()->raw_response)->toBe($payload);
})->with(['bill aliases' => false, 'short aliases' => true]);

test('a signed failed callback or incorrect amount cannot confirm an order', function (string $status, string $amount) {
    $order = Order::factory()->create(['status' => 'pending', 'total' => 25]);
    $payment = Payment::factory()->for($order)->create(['amount' => '25.00']);
    Event::fake([OrderPaymentVerified::class]);

    $this->postJson(route('payment.callback'), signedAdapterCallback($payment->provider_reference, $status, $amount))
        ->assertOk()->assertExactJson(['status' => 'OK']);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed', 'amount' => '25.00', 'paid_at' => null]);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    Event::assertNotDispatched(OrderPaymentVerified::class);
})->with(['failed' => ['failed', '25.00'], 'wrong amount' => ['paid', '24.00']]);

test('conflicting signed status aliases cannot change either payable parent', function (string $parentClass, string $signedStatus, string $otherStatus) {
    $parent = $parentClass::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create([
        'order_id' => $parent instanceof Order ? $parent->id : null,
        'reservation_id' => $parent instanceof Reservation ? $parent->id : null,
        'amount' => '25.00',
    ]);
    Event::fake([OrderPaymentVerified::class, ReservationPaymentVerified::class]);
    $payload = signedAdapterCallback($payment->provider_reference, $signedStatus);
    $payload['status'] = $otherStatus;

    $this->postJson(route('payment.callback'), $payload)->assertUnprocessable()->assertExactJson(['status' => 'FAILED']);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending', 'paid_at' => null, 'raw_response' => null]);
    $this->assertDatabaseHas($parent->getTable(), ['id' => $parent->id, 'status' => 'pending']);
    Event::assertNotDispatched(OrderPaymentVerified::class);
    Event::assertNotDispatched(ReservationPaymentVerified::class);
})->with([Order::class, Reservation::class])->with([
    'unsigned success' => ['failed', 'paid'],
    'unsigned failure' => ['paid', 'failed'],
]);

test('invalid adapter callbacks return a generic error without changing payment state', function (array $overrides) {
    $order = Order::factory()->create(['status' => 'pending', 'total' => 25]);
    $payment = Payment::factory()->for($order)->create(['amount' => '25.00']);
    Event::fake([OrderPaymentVerified::class]);
    $payload = array_replace(signedAdapterCallback($payment->provider_reference), $overrides);

    $this->postJson(route('payment.callback'), $payload)->assertUnprocessable()->assertExactJson(['status' => 'FAILED']);

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending', 'paid_at' => null, 'raw_response' => null]);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    Event::assertNotDispatched(OrderPaymentVerified::class);
})->with([
    'invalid signature' => [['signature' => 'invalid']],
    'unknown reference' => [signedAdapterCallback('unknown-payment')],
    'conflicting reference' => [['reference' => 'another-payment']],
    'conflicting amount' => [['amount' => '1.00']],
    'array status' => [['bill_status' => ['paid']]],
    'array reference' => [['bill_code' => ['reference']]],
    'array amount' => [['bill_amount' => ['25.00']]],
    'array signature' => [['signature' => ['invalid']]],
    'null status alias' => [['status' => null]],
    'missing status' => [['bill_status' => null]],
    'missing amount' => [['bill_amount' => null]],
    'missing reference' => [['bill_code' => null]],
    'empty status' => [['bill_status' => '']],
    'non numeric amount' => [['bill_amount' => 'not-money']],
]);

test('matching aliases and duplicate success preserve the first confirmation only', function () {
    $order = Order::factory()->create(['status' => 'pending', 'total' => 25]);
    $payment = Payment::factory()->for($order)->create(['amount' => '25.00']);
    Event::fake([OrderPaymentVerified::class]);
    $payload = signedAdapterCallback($payment->provider_reference);
    $payload += ['reference' => $payment->provider_reference, 'status' => 'paid', 'amount' => '25.00'];

    $this->postJson(route('payment.callback'), $payload)->assertOk();
    $firstPayment = $payment->refresh()->getAttributes();
    $order->update(['status' => 'preparing']);
    $this->postJson(route('payment.callback'), $payload)->assertOk();

    expect($payment->refresh()->getAttributes())->toBe($firstPayment);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'preparing']);
    Event::assertDispatchedTimes(OrderPaymentVerified::class, 1);
});
