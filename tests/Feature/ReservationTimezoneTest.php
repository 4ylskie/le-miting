<?php

use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\BusinessTime;
use App\Services\Payments\MockPaymentService;
use App\Services\ReservationService;
use App\Services\TableManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function timezoneBooking(Table $table, string $time = '11:00'): array
{
    return [
        'reservation_type' => 'table_only', 'table_id' => $table->id,
        'reservation_date' => '2026-10-10', 'reservation_time' => $time,
        'guest_count' => 2, 'customer_name' => 'Timezone Guest',
        'customer_email' => 'timezone@example.com', 'customer_phone' => '0123456789',
    ];
}

test('Malaysian midnight controls the business date and customer minimum date', function (string $utc, string $date) {
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));

    expect(app(BusinessTime::class)->today())->toBe($date);
    expect(config('app.timezone'))->toBe('UTC');
    $this->get(route('reservation.table'))->assertSee('min="'.$date.'"', false);
    $this->getJson(route('reservation.slots', ['date' => '2026-10-08']))->assertJsonValidationErrors('date');
})->with([
    'before midnight' => ['2026-10-09 15:59:59', '2026-10-09'],
    'midnight' => ['2026-10-09 16:00:00', '2026-10-10'],
]);

test('both levels reject the previous Malaysian date at every booking boundary', function (string $type) {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 16:00:00', 'UTC'));
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4]);
    $data = [...timezoneBooking($table), 'reservation_type' => $type, 'reservation_date' => '2026-10-09'];

    $this->get(route('reservation.availability', $data))->assertSessionHasErrors('reservation_date');
    $this->post(route('reservation.review'), $data)->assertSessionHasErrors('reservation_date');
    $this->post(route('reservation.store'), $data)->assertSessionHasErrors('reservation_date');
    $this->getJson(route('reservation.slots', ['date' => '2026-10-09']))->assertJsonValidationErrors('date');
    $this->assertDatabaseCount('reservations', 0);
    Mail::assertNothingSent();
})->with(['table_only', 'table_and_food']);

test('both levels preserve local appointment values when created around midnight', function (string $utc, string $type) {
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $data = [...timezoneBooking($table), 'reservation_type' => $type];
    if ($type === 'table_and_food') {
        $item = MenuItem::factory()->create();
        $data['food_items'] = [['menu_item_id' => $item->id, 'quantity' => 1]];
    }

    $this->get(route('reservation.availability', $data))->assertOk();
    $this->post(route('reservation.review'), $data)->assertOk()->assertSee('11:00–13:00');
    $this->post(route('reservation.store'), $data)->assertSessionHasNoErrors()->assertRedirect();

    $reservation = Reservation::firstOrFail();
    expect($reservation->reservation_date->format('Y-m-d'))->toBe('2026-10-10');
    expect(substr($reservation->reservation_time, 0, 5))->toBe('11:00');
    expect($reservation->reservation_date_time->utc()->toDateTimeString())->toBe('2026-10-10 03:00:00');
    expect($reservation->end_date_time->utc()->toDateTimeString())->toBe('2026-10-10 05:00:00');
    expect($reservation->created_at->toDateTimeString())->toBe($utc);
    Mail::assertSent(ReservationConfirmationMail::class, $type === 'table_only' ? 1 : 0);
})->with(['2026-10-09 15:59:59', '2026-10-09 16:00:00'])->with(['table_only', 'table_and_food']);

test('past and exact current Malaysian slots cannot be booked at noon', function (string $time) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 04:00:00', 'UTC'));
    Mail::fake();
    $data = timezoneBooking(Table::factory()->create(['capacity' => 4]), $time);

    $this->get(route('reservation.availability', $data))->assertSessionHasErrors('reservation_time');
    $this->post(route('reservation.review'), $data)->assertSessionHasErrors('reservation_time');
    $this->post(route('reservation.store'), $data)->assertSessionHasErrors('reservation_time');
    $this->assertDatabaseCount('reservations', 0);
    Mail::assertNothingSent();
})->with(['11:00', '12:00']);

test('local slot generation preserves opening closing and half hour rules', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 23:59:59', 'UTC'));
    $this->getJson(route('reservation.slots', ['date' => '2026-10-10']))->assertExactJson([
        'slots' => ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00'],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-10 04:00:00', 'UTC'));
    $this->getJson(route('reservation.slots', ['date' => '2026-10-10']))->assertExactJson([
        'slots' => ['12:30', '13:00', '13:30', '14:00'],
    ]);
});

test('check in and cancellation agree on the Malaysian start instant', function (string $utc, bool $canCancel, bool $canCheckIn) {
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    $reservation = Reservation::factory()->confirmed()->create([
        'reservation_date' => '2026-10-10', 'reservation_time' => '11:00:00',
    ]);

    expect(app(ReservationService::class)->canBeCancelled($reservation))->toBe($canCancel);
    expect($reservation->canCheckIn())->toBe($canCheckIn);
})->with([
    'before' => ['2026-10-10 02:59:59', true, false],
    'at start' => ['2026-10-10 03:00:00', false, true],
    'after' => ['2026-10-10 03:00:01', false, true],
]);

test('early completion protects the local interval without mutating historical timestamps', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:15:00', 'UTC'));
    Mail::fake();
    $reservation = Reservation::factory()->confirmed()->create([
        'reservation_date' => '2026-10-10', 'reservation_time' => '11:00:00', 'duration_minutes' => 120,
        'checked_in_at' => '2026-10-10 03:00:00', 'created_at' => '2026-10-09 15:00:00',
    ]);
    $before = DB::table('reservations')->find($reservation->id);
    $service = app(ReservationService::class);

    expect($service->transition($reservation->id, 'completed', 'confirmed'))->toBeNull();
    expect($service->isTableAvailable($reservation->table, CarbonImmutable::parse('2026-10-10 04:30:00', 'UTC')))->toBeFalse();
    expect($service->isTableAvailable($reservation->table, CarbonImmutable::parse('2026-10-10 05:00:00', 'UTC')))->toBeTrue();
    $management = app(TableManagementService::class);
    expect($management->protectionReason($reservation->fresh(), app(BusinessTime::class)->now()))->toBe('Active occupancy');
    $this->travelTo(CarbonImmutable::parse('2026-10-10 05:00:00', 'UTC'));
    expect($management->protectionReason($reservation->fresh(), app(BusinessTime::class)->now()))->toBeNull();

    $after = DB::table('reservations')->find($reservation->id);
    foreach (['created_at', 'checked_in_at', 'reservation_date', 'reservation_time'] as $field) {
        expect($after->$field)->toBe($before->$field);
    }
});

test('pending hold duration is absolute across Malaysian midnight', function (int $age, bool $available) {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 16:00:01', 'UTC'));
    $reservation = Reservation::factory()->withFood()->create([
        'reservation_date' => '2026-10-10', 'reservation_time' => '11:00:00',
        'status' => 'pending', 'created_at' => now('UTC')->subSeconds($age),
    ]);

    expect(app(ReservationService::class)->isTableAvailable($reservation->table, $reservation->reservation_date_time))->toBe($available);
    expect(app(TableManagementService::class)->protectionReason($reservation, app(BusinessTime::class)->now()))
        ->toBe($available ? null : 'Valid pending holds');
})->with(['899 seconds' => [899, false], '900 seconds' => [900, false], '901 seconds' => [901, true]]);

test('late callback across midnight preserves paid time and allocation recovery', function (bool $conflict) {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:59:59', 'UTC'));
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $reservation = Reservation::factory()->withFood()->create([
        'table_id' => $table->id, 'guest_count' => 2, 'reservation_date' => '2026-10-10',
        'reservation_time' => '11:00:00', 'status' => 'pending', 'created_at' => now('UTC')->subSeconds(901),
    ]);
    $reservation->reservationFoods()->create([
        'menu_item_id' => MenuItem::factory()->create()->id, 'item_name' => 'Timezone Dish', 'unit_price' => 20, 'quantity' => 1,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->create(['amount' => 20]);
    if ($conflict) {
        Reservation::factory()->confirmed()->create([
            'table_id' => $table->id, 'reservation_date' => '2026-10-10', 'reservation_time' => '11:00:00',
        ]);
    }

    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
    $this->travelTo(CarbonImmutable::parse('2026-10-09 16:00:01', 'UTC'));
    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'paid_at' => '2026-10-09 15:59:59']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'table_id' => $table->id, 'status' => $conflict ? 'pending' : 'confirmed']);
    Mail::assertSent(ReservationConfirmationMail::class, $conflict ? 0 : 1);
})->with(['free original table' => false, 'replacement booking' => true]);

test('cancellation links retain the absolute 24 hour expiry boundary', function (int $seconds, bool $valid) {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:59:59', 'UTC'));
    $reservation = Reservation::factory()->confirmed()->create(['reservation_date' => '2026-10-12', 'reservation_time' => '11:00:00']);
    $url = URL::temporarySignedRoute('reservation.cancel-form', now('UTC')->addHours(24), ['reservation' => $reservation]);
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:59:59', 'UTC')->addSeconds($seconds));

    $response = $this->get($url);
    if ($valid) {
        $response->assertOk()->assertViewIs('customer.reservations.cancel');
    } else {
        $response->assertForbidden()->assertViewIs('errors.invalid-link');
    }
})->with(['before' => [86399, true], 'exact expiry' => [86400, true], 'after' => [86401, false]]);

test('business time conversions are immutable and do not reinterpret UTC event timestamps', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 16:00:00', 'UTC'));
    $businessTime = app(BusinessTime::class);
    $utc = now('UTC');
    $local = $businessTime->local($utc);
    $local->addHours(2);

    expect($local->toDateTimeString())->toBe('2026-10-10 00:00:00');
    expect($utc->toDateTimeString())->toBe('2026-10-09 16:00:00');
    expect($businessTime->utcDayBounds('2026-10-10')[0]->toDateTimeString())->toBe('2026-10-09 16:00:00');
    expect($businessTime->utcDayBounds('2026-10-10')[1]->toDateTimeString())->toBe('2026-10-10 16:00:00');
});

test('receipt links retain the absolute expiry boundary for both payable parents', function (string $type, int $seconds, bool $valid) {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:59:59', 'UTC'));
    if ($type === 'order') {
        $parent = Order::factory()->create();
        Payment::factory()->paid()->create(['order_id' => $parent->id]);
        $route = 'orders.receipt';
        $parameters = ['order' => $parent];
    } else {
        $parent = Reservation::factory()->withFood()->confirmed()->create();
        Payment::factory()->forReservation($parent)->paid()->create();
        $route = 'reservation.receipt';
        $parameters = ['reservation' => $parent];
    }
    $url = URL::temporarySignedRoute($route, now('UTC')->addHours(24), $parameters);
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:59:59', 'UTC')->addSeconds($seconds));

    $response = $this->get($url);
    if ($valid) {
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
    } else {
        $response->assertForbidden()->assertViewIs('errors.invalid-link');
    }
})->with(['order', 'reservation'])->with(['before' => [86399, true], 'exact expiry' => [86400, true], 'after' => [86401, false]]);

test('unconfigured hours use a Malaysian date and next half hour in the fallback input', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 04:10:00', 'UTC'));
    config(['restaurant.opening_hours' => null]);

    $this->get(route('reservation.table'))->assertOk()
        ->assertSee('min="2026-10-10"', false)
        ->assertSee('type="time"', false)
        ->assertSee('data-business-date="2026-10-10"', false)
        ->assertSee('data-first-bookable-time="12:30"', false);
});
