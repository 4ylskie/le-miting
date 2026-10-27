<?php

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\Payments\MockPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function mockGatewayUrl(Payment $payment, string $route = 'mock-payment.show'): string
{
    config(['app.url' => 'http://localhost']);

    return URL::temporarySignedRoute($route, now()->addMinutes(15), ['payment' => $payment]);
}

test('a pending order redirects to a signed mock gateway', function () {
    config(['payment.driver' => 'mock', 'app.url' => 'http://localhost']);
    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->for($order)->create(['status' => 'pending']);

    $response = $this->get(route('payment.create', $payment));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/mock-payment/'.$payment->id.'?')->toContain('signature=');
});

test('a pending table and food reservation redirects to a signed mock gateway', function () {
    config(['payment.driver' => 'mock', 'app.url' => 'http://localhost']);
    $reservation = Reservation::factory()->withFood()->create(['status' => 'pending']);
    $payment = Payment::factory()->forReservation($reservation)->create(['status' => 'pending']);

    $response = $this->get(route('payment.create', $payment));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/mock-payment/'.$payment->id.'?')->toContain('signature=');
});

test('a table only reservation confirms without creating a payment gateway transaction', function () {
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 4]);

    $this->post(route('reservation.store'), [
        'reservation_type' => 'table_only',
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'guest_count' => 2,
        'customer_name' => 'Aida Rahman',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
    ])->assertRedirect();

    expect(Reservation::firstOrFail()->status)->toBe('confirmed');
    $this->assertDatabaseCount('payments', 0);
});

test('unsigned and tampered mock gateway URLs are rejected', function () {
    config(['payment.driver' => 'mock', 'app.url' => 'http://localhost']);
    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->for($order)->create(['status' => 'pending']);

    $this->get(route('mock-payment.show', $payment))
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');

    $this->get(route('mock-payment.show', $payment).'?expires='.now()->addMinutes(15)->timestamp.'&signature=invalid')
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');
});

test('the payment gateway displays authoritative transaction details and standard payment controls', function () {
    config(['payment.driver' => 'mock']);
    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->for($order)->create(['status' => 'pending', 'amount' => 27.50]);

    $this->get(mockGatewayUrl($payment))
        ->assertOk()
        ->assertSee('Le Miting Payment Gateway')
        ->assertSee('Online Payment Verification')
        ->assertDontSee('Demo')->assertDontSee('Simulated')
        ->assertSee($payment->provider_reference)
        ->assertSee('RM 27.50')
        ->assertSee('Online Banking')
        ->assertSee('Credit / Debit Card')
        ->assertSee('E-Wallet')
        ->assertDontSee('name="amount"', false)
        ->assertDontSee('name="reference"', false)
        ->assertDontSee('name="signature"', false)
        ->assertDontSee('name="card_number"', false)
        ->assertDontSee('name="cvv"', false)
        ->assertDontSee('name="password"', false)
        ->assertDontSee('name="otp"', false);
});

test('a successful mock gateway result confirms a pending order and sends one confirmation mail', function () {
    Mail::fake();
    config(['payment.driver' => 'mock']);
    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->for($order)->create(['status' => 'pending', 'amount' => $order->total]);

    $this->post(mockGatewayUrl($payment, 'mock-payment.process'), [
        'payment_method' => 'card',
        'outcome' => 'success',
    ])->assertRedirect(route('payment.return', ['reference' => $payment->provider_reference]));

    expect($payment->refresh()->status)->toBe('paid');
    expect($order->refresh()->status)->toBe('confirmed');
    Mail::assertSent(OrderConfirmationMail::class, 1);
});

test('a successful mock gateway result confirms a pending table and food reservation', function () {
    config(['payment.driver' => 'mock']);
    $reservation = Reservation::factory()->withFood()->create([
        'status' => 'pending',
        'table_id' => Table::factory()->create(['capacity' => 4])->id,
        'guest_count' => 2,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->create(['status' => 'pending']);

    $this->post(mockGatewayUrl($payment, 'mock-payment.process'), [
        'payment_method' => 'ewallet',
        'outcome' => 'success',
    ])->assertRedirect(route('payment.return', ['reference' => $payment->provider_reference]));

    expect($payment->refresh()->status)->toBe('paid');
    expect($reservation->refresh()->status)->toBe('confirmed');
});

test('failed and abandoned mock gateway interactions leave the parent unconfirmed', function (string $outcome, string $paymentStatus) {
    config(['payment.driver' => 'mock']);
    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->for($order)->create(['status' => 'pending']);

    $this->post(mockGatewayUrl($payment, 'mock-payment.process'), [
        'payment_method' => 'online_banking',
        'outcome' => $outcome,
    ])->assertRedirect(route('payment.return', ['reference' => $payment->provider_reference]));

    expect($payment->refresh()->status)->toBe($paymentStatus);
    expect($order->refresh()->status)->toBe('pending');
})->with([
    'failure' => ['failure', 'failed'],
    'abandonment' => ['abandoned', 'pending'],
]);

test('a tampered callback payload and changed FIUU configuration cannot alter mock verification', function () {
    config(['payment.driver' => 'mock', 'payment.fiuu.secret_key' => 'first-fiuu-secret']);
    $firstOrder = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->for($firstOrder)->create(['status' => 'pending']);
    $service = app(MockPaymentService::class);
    $payload = $service->callbackPayload($payment, 'success');

    config(['payment.fiuu.secret_key' => 'second-fiuu-secret']);
    expect($service->verify($payload)->status)->toBe('paid');

    $secondOrder = Order::factory()->create(['status' => 'pending']);
    $tamperedPayment = Payment::factory()->for($secondOrder)->create(['status' => 'pending']);
    $tampered = $service->callbackPayload($tamperedPayment, 'success');
    $tampered['amount'] = '0.01';

    $this->post(route('payment.callback'), $tampered)->assertUnprocessable();
    expect($tamperedPayment->refresh()->status)->toBe('pending');
});

test('browser return query data cannot mark a pending payment as paid', function () {
    $order = Order::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->for($order)->create(['status' => 'pending']);

    $this->get(route('payment.return', ['reference' => $payment->provider_reference, 'status' => 'paid']))
        ->assertOk()
        ->assertSee('Verifying your payment');

    expect($payment->refresh()->status)->toBe('pending');
});

test('a failed payment retry reuses its parent and enters a fresh signed mock gateway', function () {
    config(['payment.driver' => 'mock', 'app.url' => 'http://localhost']);
    $order = Order::factory()->create(['status' => 'pending', 'total' => 22.00]);
    $payment = Payment::factory()->for($order)->create(['status' => 'failed', 'amount' => 22.00]);
    $previousReference = $payment->provider_reference;

    $this->post(route('payment.retry', $payment))->assertRedirect(route('payment.create', $payment));

    $gateway = $this->get(route('payment.create', $payment));
    $gateway->assertRedirect();

    expect($payment->refresh()->provider_reference)->not->toBe($previousReference);
    expect($payment->status)->toBe('pending');
    expect(Order::count())->toBe(1);
    expect($gateway->headers->get('Location'))->toContain('/mock-payment/'.$payment->id.'?')->toContain('signature=');
});
