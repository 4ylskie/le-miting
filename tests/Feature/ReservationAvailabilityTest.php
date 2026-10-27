<?php

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\BusinessTime;
use App\Services\ReservationService;
use App\Services\TableManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** Count the measured operation only; cap stays constant for 1, 10, 50 and 100 tables. */
function withBoundedAvailabilityQueries(Closure $operation, int $maximum): mixed
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $result = $operation();
        expect(count(DB::getQueryLog()))->toBeGreaterThan(0)->toBeLessThanOrEqual($maximum);

        return $result;
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

/** @return Collection<int, Table> */
function availabilityTables(int $count): Collection
{
    $attributes = Table::factory()->raw(['capacity' => 4, 'status' => 'available']);

    return collect(range(1, $count))->map(fn (int $number): Table => Table::create([
        ...$attributes, 'table_number' => 'AVAIL-'.$number,
    ]));
}

/** @param array<string, mixed> $attributes */
function availabilityReservation(Table $table, array $attributes = []): Reservation
{
    return Reservation::factory()->confirmed()->create([
        'table_id' => $table->id, 'guest_count' => 2,
        'reservation_date' => '2026-10-10', 'reservation_time' => '11:00:00',
        'duration_minutes' => 120, 'created_at' => now('UTC'),
        ...$attributes,
    ]);
}

test('customer availability uses bounded queries as the number of candidate tables grows', function (int $count) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $tables = availabilityTables($count);
    foreach ($tables as $table) {
        availabilityReservation($table, ['reservation_date' => '2025-01-01']);
    }
    availabilityReservation($tables->last(), ['reservation_date' => '2026-10-11', 'status' => 'completed']);
    $start = CarbonImmutable::parse('2026-10-11 11:00:00', 'Asia/Kuala_Lumpur');
    $availability = withBoundedAvailabilityQueries(fn () => app(ReservationService::class)->tableAvailability($start, 2), 4);

    expect($availability['tables']->pluck('id')->all())->toBe($tables->slice(0, -1)->pluck('id')->all());
    expect($availability['has_suitable_tables'])->toBeTrue();
    expect($availability['party_too_large'])->toBeFalse();
})->with([1, 10, 50, 100]);

test('admin reassignment renders candidates with bounded queries for the entire request', function (int $count) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $tables = availabilityTables($count);
    foreach ($tables as $table) {
        availabilityReservation($table, ['reservation_date' => '2025-01-01']);
    }
    availabilityReservation($tables->last(), ['reservation_date' => '2026-10-11', 'status' => 'completed']);
    $source = Table::factory()->create(['table_number' => 'SOURCE', 'capacity' => 4]);
    $reservation = availabilityReservation($source, ['reservation_date' => '2026-10-11']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    withBoundedAvailabilityQueries(fn () => $this->get(route('admin.reservations.show', $reservation)), 8)
        ->assertOk()
        ->assertViewHas('availableTables', fn ($actual) => $actual->pluck('id')->all() === $tables->slice(0, -1)->pluck('id')->all());
})->with([1, 10, 50, 100]);

test('the complete customer availability request keeps bounded queries including the floor plan', function (int $count) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $tables = availabilityTables($count);
    availabilityReservation($tables->last(), ['reservation_date' => '2026-10-11']);
    withBoundedAvailabilityQueries(fn () => $this->get(route('reservation.availability', [
        'reservation_type' => 'table_only', 'reservation_date' => '2026-10-11',
        'reservation_time' => '11:00', 'guest_count' => 2,
    ])), 5)->assertOk()->assertViewHas('availableTables', fn ($actual) => $actual->pluck('id')->all() === $tables->slice(0, -1)->pluck('id')->all());
})->with([1, 10, 50, 100]);

test('availability previews match the authoritative interval and hold rules', function (array $attributes, string $requested, bool $available) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $table = availabilityTables(1)->first();
    availabilityReservation($table, $attributes);
    $start = CarbonImmutable::parse($requested, 'Asia/Kuala_Lumpur');
    $service = app(ReservationService::class);

    expect($service->tableAvailability($start, 2)['tables']->pluck('id')->all())->toBe($available ? [$table->id] : []);
    expect($service->isTableAvailable($table, $start))->toBe($available);
})->with([
    'confirmed future' => [['reservation_date' => '2026-10-11'], '2026-10-11 12:00', false],
    'confirmed ongoing' => [[], '2026-10-10 12:00', false],
    'confirmed historical' => [['reservation_date' => '2025-01-01'], '2026-10-10 12:00', true],
    'checked in' => [['checked_in_at' => '2026-10-10 03:00:00'], '2026-10-10 12:00', false],
    'completed inside interval' => [['status' => 'completed'], '2026-10-10 12:00', false],
    'completed after interval' => [['status' => 'completed'], '2026-10-10 13:00', true],
    'cancelled' => [['status' => 'cancelled'], '2026-10-10 12:00', true],
    'hold at 899 seconds' => [['status' => 'pending', 'created_at' => '2026-10-10 03:15:01'], '2026-10-10 12:00', false],
    'hold at 900 seconds' => [['status' => 'pending', 'created_at' => '2026-10-10 03:15:00'], '2026-10-10 12:00', false],
    'hold at 901 seconds' => [['status' => 'pending', 'created_at' => '2026-10-10 03:14:59'], '2026-10-10 12:00', true],
    'adjacent before' => [[], '2026-10-10 09:00', true],
    'adjacent after' => [[], '2026-10-10 13:00', true],
    'one second overlap before' => [[], '2026-10-10 09:00:01', false],
    'one second overlap after' => [[], '2026-10-10 12:59:59', false],
    'historical longer duration' => [['reservation_date' => '2026-10-08', 'reservation_time' => '13:00:00', 'duration_minutes' => 2880], '2026-10-10 12:00', false],
    'historical shorter duration' => [['duration_minutes' => 30], '2026-10-10 12:00', true],
    'historical midnight overlap' => [['reservation_date' => '2026-10-09', 'reservation_time' => '23:30:00'], '2026-10-10 00:30', false],
    'historical midnight adjacency' => [['reservation_date' => '2026-10-09', 'reservation_time' => '22:30:00'], '2026-10-10 00:30', true],
]);

test('expired paid pending commitments remain available for allocation but protected for management', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $table = availabilityTables(1)->first();
    $reservation = availabilityReservation($table, [
        'reservation_type' => 'table_and_food', 'status' => 'pending',
        'created_at' => '2026-10-10 03:14:59',
    ]);
    Payment::factory()->forReservation($reservation)->paid()->create();

    expect(app(ReservationService::class)->tableAvailability($reservation->reservation_date_time, 2)['tables']->pluck('id')->all())->toBe([$table->id]);
    expect(app(TableManagementService::class)->protectionReason($reservation->load('payment'), app(BusinessTime::class)->now()))
        ->toBe('Paid pending — manual resolution');
});

test('availability previews keep UTC holds and Malaysia appointment dates across midnight', function (string $created, bool $available) {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 16:00:01', 'UTC'));
    $table = availabilityTables(1)->first();
    availabilityReservation($table, ['status' => 'pending', 'created_at' => $created, 'reservation_time' => '00:30:00']);
    $start = CarbonImmutable::parse('2026-10-09 16:30:00', 'UTC');

    expect(app(ReservationService::class)->tableAvailability($start, 2)['tables']->pluck('id')->all())->toBe($available ? [$table->id] : []);
})->with([
    '899 seconds' => ['2026-10-09 15:45:02', false],
    '900 seconds' => ['2026-10-09 15:45:01', false],
    '901 seconds' => ['2026-10-09 15:45:00', true],
]);

test('customer and admin previews preserve suitability maintenance and conflicting commitments', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $tables = availabilityTables(6);
    [$source, $suitable, $small, $tooLarge, $maintenance, $blocked] = $tables->all();
    $small->update(['capacity' => 2]);
    $tooLarge->update(['capacity' => 10]);
    $maintenance->update(['status' => 'maintenance']);
    $reservation = availabilityReservation($source, ['guest_count' => 3, 'reservation_date' => '2026-10-11']);
    availabilityReservation($blocked, ['reservation_date' => '2026-10-11', 'status' => 'completed']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->get(route('reservation.availability', [
        'reservation_type' => 'table_only', 'reservation_date' => '2026-10-11',
        'reservation_time' => '11:00', 'guest_count' => 3,
    ]))->assertOk()->assertViewHas('availableTables', fn ($actual) => $actual->pluck('id')->all() === [$suitable->id]);
    $this->get(route('admin.reservations.show', $reservation))
        ->assertOk()->assertViewHas('availableTables', fn ($actual) => $actual->pluck('id')->all() === [$suitable->id]);
});

test('empty and unsuitable table sets retain the customer availability flags', function (int $guests, bool $suitable, bool $tooLarge) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $table = availabilityTables(1)->first();
    $table->update(['capacity' => 6, 'status' => 'maintenance']);

    $result = app(ReservationService::class)->tableAvailability(CarbonImmutable::parse('2026-10-11 11:00', 'Asia/Kuala_Lumpur'), $guests);

    expect($result['tables'])->toBeEmpty();
    expect($result['has_suitable_tables'])->toBe($suitable);
    expect($result['party_too_large'])->toBe($tooLarge);
})->with(['below minimum' => [2, false, false], 'suitable maintenance' => [3, true, false], 'above maximum' => [7, false, true]]);

test('a restaurant without tables has no suitable tables and no maximum party warning', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $this->expectsDatabaseQueryCount(1);

    $result = app(ReservationService::class)->tableAvailability(CarbonImmutable::parse('2026-10-11 11:00', 'Asia/Kuala_Lumpur'), 2);

    expect($result['tables'])->toBeEmpty();
    expect($result['has_suitable_tables'])->toBeFalse();
    expect($result['party_too_large'])->toBeFalse();
});

test('excluding a reservation from its preview never excludes another overlapping reservation', function (string $otherTime, bool $available) {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    $tables = availabilityTables(1);
    $table = $tables->first();
    $reservation = availabilityReservation($table);
    availabilityReservation($table, ['reservation_time' => $otherTime]);
    $service = app(ReservationService::class);

    expect($service->availableTablesForInterval($tables, $reservation->reservation_date_time, $reservation->id)->pluck('id')->all())
        ->toBe($available ? [$table->id] : []);
    expect($service->isTableAvailable($table, $reservation->reservation_date_time, $reservation->id))->toBe($available);
    expect($service->availableTablesForInterval($tables, $reservation->reservation_date_time))->toBeEmpty();
})->with(['other overlaps' => ['12:00:00', false], 'other adjacent' => ['13:00:00', true]]);

test('availability avoids hydrating unrelated history and preserves every historical record', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    [$candidate, $unrelated] = availabilityTables(2)->all();
    $unrelated->update(['status' => 'maintenance']);
    Reservation::factory()->confirmed()->count(100)->create([
        'table_id' => $candidate->id, 'guest_count' => 2, 'reservation_date' => '2025-01-01',
        'reservation_time' => '11:00:00', 'duration_minutes' => 120,
    ]);
    availabilityReservation($unrelated);
    availabilityReservation($candidate, ['reservation_date' => '2027-01-01']);
    availabilityReservation($candidate, ['status' => 'cancelled']);
    availabilityReservation($candidate, ['status' => 'pending', 'created_at' => '2026-10-10 03:14:59']);
    $overlap = availabilityReservation($candidate);
    $before = DB::table('reservations')->orderBy('id')->get()->toArray();
    $hydratedIds = [];
    Reservation::retrieved(function (Reservation $reservation) use (&$hydratedIds): void {
        $hydratedIds[] = $reservation->id;
    });

    $result = app(ReservationService::class)->tableAvailability(CarbonImmutable::parse('2026-10-10 12:00', 'Asia/Kuala_Lumpur'), 2);

    expect($result['tables'])->toBeEmpty();
    expect($hydratedIds)->toBe([$overlap->id]);
    expect(DB::table('reservations')->orderBy('id')->get()->toArray())->toEqual($before);
});

test('booking submission revalidates a table after a successful availability preview', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    Mail::fake();
    $table = availabilityTables(1)->first();
    $data = [
        'reservation_type' => 'table_only', 'reservation_date' => '2026-10-11',
        'reservation_time' => '11:00', 'guest_count' => 2, 'table_id' => $table->id,
        'customer_name' => 'Preview Guest', 'customer_email' => 'preview@example.com', 'customer_phone' => '0123456789',
    ];
    $this->get(route('reservation.availability', $data))
        ->assertOk()->assertViewHas('availableTables', fn ($actual) => $actual->pluck('id')->all() === [$table->id]);
    availabilityReservation($table, ['reservation_date' => '2026-10-11']);

    $this->post(route('reservation.store'), $data)->assertSessionHasErrors('table_id');

    $this->assertDatabaseCount('reservations', 1);
    Mail::assertNothingSent();
});

test('admin reassignment revalidates the destination after rendering available candidates', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 03:30:00', 'UTC'));
    Mail::fake();
    [$source, $destination] = availabilityTables(2)->all();
    $reservation = availabilityReservation($source, ['reservation_date' => '2026-10-11']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get(route('admin.reservations.show', $reservation))
        ->assertOk()->assertViewHas('availableTables', fn ($actual) => $actual->pluck('id')->all() === [$destination->id]);
    availabilityReservation($destination, ['reservation_date' => '2026-10-11']);

    $this->patch(route('admin.reservations.update-table', $reservation), [
        'table_id' => $destination->id, 'expected_table_id' => $source->id, 'expected_status' => 'confirmed',
    ])->assertSessionHasErrors('table_id');

    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'table_id' => $source->id, 'status' => 'confirmed']);
    Mail::assertNothingSent();
});
