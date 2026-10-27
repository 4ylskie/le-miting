<?php

use App\Mail\GoogleReviewInvitationMail;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\Payments\MockPaymentService;
use App\Services\ReportService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $attributes */
function arrivalReservation(array $attributes = []): Reservation
{
    return Reservation::factory()->confirmed()->create([
        'table_id' => Table::factory()->create(['capacity' => 4, 'status' => 'available'])->id,
        'guest_count' => 2,
        'reservation_date' => '2026-10-09',
        'reservation_time' => '11:00:00',
        'duration_minutes' => 120,
        ...$attributes,
    ]);
}

test('the additive arrival migration preserves historical records without inventing timestamps', function () {
    $migration = require database_path('migrations/2026_10_08_174317_add_checked_in_at_to_reservations_table.php');
    $migration->down();
    $reservation = arrivalReservation(['status' => 'completed']);
    $before = $reservation->refresh()->getAttributes();

    $migration->up();

    expect(Schema::hasColumn('reservations', 'checked_in_at'))->toBeTrue();
    $after = $reservation->refresh()->getAttributes();
    expect($after['checked_in_at'])->toBeNull();
    unset($after['checked_in_at']);
    expect($after)->toBe($before);
});

test('arrival is separate from completion for both reservation levels', function (string $type) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $reservation = arrivalReservation(['reservation_type' => $type]);
    $payment = $type === 'table_and_food' ? Payment::factory()->forReservation($reservation)->paid()->create() : null;
    $paymentBefore = $payment?->refresh()->getAttributes();
    $beforeCount = app(ReportService::class)->reservations(now()->subDay(), now())['completed_reservations'];

    $this->actingAs($admin)->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number])
        ->assertRedirect(route('admin.reservations.show', $reservation));

    $reservation->refresh();
    expect($reservation->checked_in_at)->toBeInstanceOf(Carbon::class);
    expect($reservation->checked_in_at->toDateTimeString())->toBe('2026-10-09 03:00:00');
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed', 'duration_minutes' => 120, 'reservation_time' => '11:00:00']);
    expect($payment?->refresh()->getAttributes())->toBe($paymentBefore);
    expect(app(ReportService::class)->reservations(now()->subDay(), now())['completed_reservations'])->toBe($beforeCount);
    Mail::assertNothingSent();

    $this->travelTo('2026-10-09 04:15:00');
    $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])->assertSessionHas('status');
    $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])->assertSessionHas('error');

    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'completed', 'checked_in_at' => '2026-10-09 03:00:00']);
    expect(app(ReportService::class)->reservations(now()->subDay(), now())['completed_reservations'])->toBe($beforeCount + 1);
    Mail::assertSent(GoogleReviewInvitationMail::class, 1);
})->with(['table_only', 'table_and_food']);

test('check in uses Malaysia scheduled time without an early or arbitrary late window', function (string $utcTime, bool $eligible) {
    $this->travelTo($utcTime);
    Mail::fake();
    $reservation = arrivalReservation();
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $response = $this->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number]);

    if ($eligible) {
        $response->assertSessionHas('status');
        expect($reservation->refresh()->checked_in_at->equalTo(now()))->toBeTrue();
    } else {
        $response->assertSessionHasErrors('reservation_number');
        expect($reservation->refresh()->checked_in_at)->toBeNull();
    }
    expect($reservation->status)->toBe('confirmed');
    expect(config('app.timezone'))->toBe('UTC');
    Mail::assertNothingSent();
})->with([
    'one second early' => ['2026-10-09 02:59:59', false],
    'exact start' => ['2026-10-09 03:00:00', true],
    'after start' => ['2026-10-09 03:05:00', true],
    'no invented late cutoff' => ['2026-10-10 03:00:00', true],
]);

test('duplicate check in cannot overwrite arrival or send an invitation', function () {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $reservation = arrivalReservation();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number])->assertSessionHas('status');
    $this->travelTo('2026-10-09 03:10:00');

    $this->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number])
        ->assertSessionHasErrors(['reservation_number' => 'This reservation is already checked in.']);

    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed', 'checked_in_at' => '2026-10-09 03:00:00']);
    Mail::assertNothingSent();
});

test('completion without arrival is rejected by the backend', function () {
    Mail::fake();
    $reservation = arrivalReservation();

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])
        ->assertSessionHas('error', 'Check in this reservation before marking the visit completed.');

    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed', 'checked_in_at' => null]);
    Mail::assertNothingSent();
});

test('confirmed checked in completed and historical visits retain their half open occupancy interval', function (string $status, bool $arrived) {
    $this->travelTo('2026-10-09 04:15:00');
    Mail::fake();
    $reservation = arrivalReservation(['status' => $status, 'checked_in_at' => $arrived ? now()->subHour() : null]);
    $service = app(ReservationService::class);

    expect($service->isTableAvailable($reservation->table, Carbon::parse('2026-10-09 12:30:00', 'Asia/Kuala_Lumpur')))->toBeFalse();
    expect($service->isTableAvailable($reservation->table, Carbon::parse('2026-10-09 13:00:00', 'Asia/Kuala_Lumpur')))->toBeTrue();
    expect($service->isTableAvailable($reservation->table, Carbon::parse('2026-10-09 09:00:00', 'Asia/Kuala_Lumpur')))->toBeTrue();

    $late = Reservation::factory()->withFood()->create([
        'table_id' => $reservation->table_id, 'guest_count' => 2,
        'reservation_date' => '2026-10-09', 'reservation_time' => '12:30',
        'status' => 'pending', 'created_at' => now()->subMinutes(16),
    ]);
    $payment = Payment::factory()->forReservation($late)->create();
    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    $this->assertDatabaseHas('reservations', ['id' => $late->id, 'status' => 'pending']);
    Mail::assertNothingSent();
})->with([
    'confirmed' => ['confirmed', false], 'arrived' => ['confirmed', true],
    'early completion' => ['completed', true], 'historical completion' => ['completed', false],
]);

test('arrival blocks customer cancellation admin cancellation and reassignment', function (string $action) {
    $this->travelTo('2026-10-09 03:05:00');
    Mail::fake();
    $reservation = arrivalReservation(['checked_in_at' => now()->subMinutes(5)]);
    $before = $reservation->refresh()->getAttributes();
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    if ($action === 'customer cancellation') {
        $url = URL::temporarySignedRoute('reservation.cancel', now()->addHour(), ['reservation' => $reservation]);
        $this->post($url)->assertUnprocessable()->assertSee('has been checked in');
    } elseif ($action === 'admin cancellation') {
        $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'cancelled', 'expected_status' => $reservation->status])->assertSessionHas('error');
    } else {
        $target = Table::factory()->create(['capacity' => 4]);
        $this->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $target->id, 'expected_table_id' => $reservation->table_id, 'expected_status' => $reservation->status])->assertSessionHas('error');
    }

    expect($reservation->refresh()->getAttributes())->toBe($before);
    Mail::assertNothingSent();
})->with(['customer cancellation', 'admin cancellation', 'reassignment']);

test('a stale bound reservation cannot bypass an arrival that won before mutation', function (string $action) {
    $this->travelTo('2026-10-09 03:05:00');
    Mail::fake();
    $reservation = arrivalReservation();
    $target = Table::factory()->create(['capacity' => 4]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $interleaved = false;
    DB::listen(function ($query) use (&$interleaved, $reservation) {
        if (! $interleaved && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from '.DB::connection()->getQueryGrammar()->wrapTable('reservations'))) {
            $interleaved = true;
            DB::table('reservations')->where('id', $reservation->id)->update(['checked_in_at' => now()]);
        }
    });

    if ($action === 'customer cancellation') {
        $url = URL::temporarySignedRoute('reservation.cancel', now()->addHour(), ['reservation' => $reservation]);
        $this->post($url)->assertUnprocessable();
    } elseif ($action === 'admin cancellation') {
        $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'cancelled', 'expected_status' => $reservation->status])->assertSessionHas('error');
    } else {
        $this->patch(route('admin.reservations.update-table', $reservation), ['table_id' => $target->id, 'expected_table_id' => $reservation->table_id, 'expected_status' => $reservation->status])->assertSessionHas('error');
    }

    expect($interleaved)->toBeTrue();
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed', 'table_id' => $reservation->table_id, 'checked_in_at' => now()->toDateTimeString()]);
    Mail::assertNothingSent();
})->with(['customer cancellation', 'admin cancellation', 'reassignment']);

test('completion mail waits for commit and is discarded on rollback', function () {
    $this->travelTo('2026-10-09 04:00:00');
    Mail::fake();
    $reservation = arrivalReservation(['checked_in_at' => now()->subHour()]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    DB::beginTransaction();

    $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])->assertSessionHas('status');
    Mail::assertNothingSent();
    DB::rollBack();

    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
    Mail::assertNothingSent();
    $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])->assertSessionHas('status');
    $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])->assertSessionHas('error');
    Mail::assertSent(GoogleReviewInvitationMail::class, 1);
});

test('admin action visibility reflects arrival without adding a stored status', function () {
    $this->travelTo('2026-10-09 02:59:59');
    Mail::fake();
    $reservation = arrivalReservation();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get(route('admin.reservations.show', $reservation))->assertDontSee('>Check In<', false)
        ->assertDontSee('Mark Completed')->assertViewHas('nextStatuses', ['cancelled']);
    $this->travelTo('2026-10-09 03:00:00');
    $this->get(route('admin.reservations.show', $reservation))->assertSee('>Check In<', false)->assertSee('Not checked in');
    $this->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number])->assertSessionHas('status');

    $this->get(route('admin.reservations.show', $reservation))->assertSee('Mark Completed')->assertSee('11:00 AM')
        ->assertDontSee('>Check In<', false)->assertDontSee('Reassign table')->assertViewHas('nextStatuses', ['completed']);
    $this->get(route('admin.reservations.index'))->assertSee('Checked in')->assertDontSee('>Check In<', false);
    $historical = arrivalReservation(['status' => 'completed']);
    $this->get(route('admin.reservations.show', $historical))->assertSee('Arrival not recorded')->assertDontSee('Mark Completed');
});
