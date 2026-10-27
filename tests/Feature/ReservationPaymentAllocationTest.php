<?php

use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\PaymentRetryService;
use App\Services\Payments\MockPaymentService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function allocationReservation(int $ageSeconds = 960, ?Table $table = null): Reservation
{
    return Reservation::factory()->withFood()->create([
        'table_id' => ($table ?? Table::factory()->create(['capacity' => 4, 'status' => 'available']))->id,
        'guest_count' => 2,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '11:00:00',
        'duration_minutes' => 120,
        'status' => 'pending',
        'created_at' => now()->subSeconds($ageSeconds),
    ]);
}

function allocationPayment(Reservation $reservation): Payment
{
    $reservation->reservationFoods()->create([
        'menu_item_id' => MenuItem::factory()->create()->id,
        'item_name' => 'Allocation test dish', 'unit_price' => 25, 'quantity' => 1,
    ]);

    return Payment::factory()->forReservation($reservation)->create(['amount' => '25.00']);
}

/** @return array<string, mixed> */
function allocationBookingData(Table $table): array
{
    return [
        'table_id' => $table->id,
        'guest_count' => 2,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '11:00',
        'customer_name' => 'Replacement Guest',
        'customer_email' => 'replacement@example.com',
        'customer_phone' => '0123456789',
    ];
}

test('a suitable allocation confirms once before at and after hold expiry', function (int $ageSeconds) {
    $this->travelTo('2026-10-08 09:00:00');
    Mail::fake();
    $reservation = allocationReservation($ageSeconds);
    $createdAt = $reservation->created_at;
    $payment = allocationPayment($reservation);
    $service = app(ReservationService::class);
    expect($service->isTableAvailable($reservation->table, $reservation->reservation_date_time))->toBe($ageSeconds > 900);

    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'amount' => '25.00']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed', 'table_id' => $reservation->table_id]);
    expect($reservation->refresh()->created_at->equalTo($createdAt))->toBeTrue();
    expect($service->isTableAvailable($reservation->table, $reservation->reservation_date_time))->toBeFalse();
    Mail::assertSent(ReservationConfirmationMail::class, 1);
    $this->assertDatabaseCount('payments', 1);
})->with(['fresh' => 0, 'before expiry' => 899, 'at expiry' => 900, 'after expiry' => 901, 'expired' => 960]);

test('a replacement booking wins over a late callback without losing the verified payment', function () {
    $this->travelTo('2026-10-08 09:00:00');
    Mail::fake();
    $reservation = allocationReservation();
    $original = $reservation->refresh()->getAttributes();
    $payment = allocationPayment($reservation);
    $replacement = app(ReservationService::class)->createTableOnly(allocationBookingData($reservation->table));
    Mail::assertSent(ReservationConfirmationMail::class, fn ($mail) => $mail->reservation->id === $replacement->id);
    Mail::fake();

    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
    $paidAttributes = $payment->refresh()->getAttributes();
    $this->assertDatabaseHas('reservations', ['id' => $replacement->id, 'status' => 'confirmed']);
    $replacement->update(['status' => 'cancelled']);
    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');

    expect($reservation->refresh()->getAttributes())->toBe($original);
    expect($payment->refresh()->getAttributes())->toBe($paidAttributes);
    expect($payment->status)->toBe('paid');
    expect($payment->paid_at)->not->toBeNull();
    expect($payment->raw_response['status'])->toBe('paid');
    expect(app(PaymentRetryService::class)->canRetry($payment))->toBeFalse();
    expect(fn () => app(PaymentRetryService::class)->retry($payment))->toThrow(ValidationException::class);
    Mail::assertNothingSent();
    $this->assertDatabaseCount('payments', 1);
});

test('late payment cannot take a table occupied by a confirmed reservation or live hold', function (string $status, int $ageSeconds, bool $blocks) {
    $this->travelTo('2026-10-08 09:00:00');
    Mail::fake();
    $reservation = allocationReservation();
    $other = allocationReservation($ageSeconds, $reservation->table);
    $other->update(['status' => $status]);
    $otherAttributes = $other->refresh()->getAttributes();
    $payment = allocationPayment($reservation);

    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => $blocks ? 'pending' : 'confirmed']);
    expect($other->refresh()->getAttributes())->toBe($otherAttributes);
    Mail::assertSent(ReservationConfirmationMail::class, $blocks ? 0 : 1);
})->with([
    'confirmed' => ['confirmed', 3600, true],
    'live pending' => ['pending', 899, true],
    'boundary pending' => ['pending', 900, true],
    'expired pending' => ['pending', 901, false],
]);

test('payment retains success when the original table is no longer suitable or available', function (int $capacity, string $status, int $guestCount, int $holdAge) {
    $this->travelTo('2026-10-08 09:00:00');
    Mail::fake();
    $reservation = allocationReservation($holdAge);
    $reservation->update(['guest_count' => $guestCount]);
    $reservation->table->update(['capacity' => $capacity, 'status' => $status]);
    $payment = allocationPayment($reservation);

    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending', 'table_id' => $reservation->table_id]);
    Mail::assertNothingSent();
})->with([
    'maintenance' => [4, 'maintenance', 2],
    'below minimum party' => [6, 'available', 2],
    'unsupported capacity' => [5, 'available', 2],
    'too small' => [2, 'available', 3],
])->with(['live hold' => 60, 'expired hold' => 960]);

test('touching intervals do not block a late payment allocation', function (string $otherTime) {
    $this->travelTo('2026-10-08 09:00:00');
    Mail::fake();
    $reservation = allocationReservation();
    $other = allocationReservation(0, $reservation->table);
    $other->update(['status' => 'confirmed', 'reservation_time' => $otherTime]);
    $payment = allocationPayment($reservation);

    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');

    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
    Mail::assertSent(ReservationConfirmationMail::class, 1);
})->with(['ends at start' => '09:00', 'starts at end' => '13:00']);

test('the first late payment allocation blocks a competing payment and new booking', function () {
    $this->travelTo('2026-10-08 09:00:00');
    Mail::fake();
    $first = allocationReservation();
    $second = allocationReservation(960, $first->table);
    $firstPayment = allocationPayment($first);
    $secondPayment = allocationPayment($second);

    app(MockPaymentService::class)->processDemoOutcome($firstPayment, 'success');
    app(MockPaymentService::class)->processDemoOutcome($secondPayment, 'success');

    expect(fn () => app(ReservationService::class)->createTableOnly(allocationBookingData($first->table)))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseHas('reservations', ['id' => $first->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('reservations', ['id' => $second->id, 'status' => 'pending']);
    $this->assertDatabaseHas('payments', ['id' => $secondPayment->id, 'status' => 'paid']);
    $this->assertDatabaseCount('reservations', 2);
    Mail::assertSent(ReservationConfirmationMail::class, 1);
});

test('paid allocation conflicts acknowledge callbacks and show an honest customer return', function (string $driver) {
    $this->travelTo('2026-10-08 09:00:00');
    Mail::fake();
    config(['payment.driver' => $driver, 'payment.fiuu.secret_key' => 'allocation-test-secret']);
    $reservation = allocationReservation();
    $reservation->table->update(['status' => 'maintenance']);
    $payment = allocationPayment($reservation);
    $payload = $driver === 'mock'
        ? app(MockPaymentService::class)->callbackPayload($payment, 'success')
        : [
            'reference' => $payment->provider_reference, 'amount' => '25.00', 'status' => 'paid',
            'signature' => md5('allocation-test-secret'.$payment->provider_reference.'25.00paid'),
        ];

    $response = $this->post(route('payment.callback'), $payload);
    if ($driver === 'fiuu') {
        $response->assertOk()->assertExactJson(['status' => 'OK']);
    } else {
        $response->assertRedirect(route('payment.return', ['reference' => $payment->provider_reference]));
    }

    $this->get(route('payment.return', ['reference' => $payment->provider_reference]))
        ->assertOk()->assertSee('Payment received, reservation not confirmed')->assertDontSee('Verifying your payment');
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending']);
    Mail::assertNothingSent();
})->with(['fiuu', 'mock']);
