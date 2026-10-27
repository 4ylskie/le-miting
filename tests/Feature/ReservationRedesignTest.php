<?php

use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\ReservationSlots;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('table illustrations use actual capacity and keep one accessible control per table', function (int $capacity) {
    $table = Table::factory()->create(['table_number' => 'T1', 'capacity' => $capacity, 'status' => 'available']);
    $response = $this->get(route('reservation.availability', [...redesignBooking($table), 'guest_count' => $capacity]))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $unit = $xpath->query('//label[@data-floor-table="'.$table->id.'"]')->item(0);

    expect($xpath->query('.//*[@data-chair]', $unit)->length)->toBe($capacity)
        ->and($xpath->query('.//input[@type="radio" and @name="table_id"]', $unit)->length)->toBe(1)
        ->and($xpath->evaluate('string(.//input/@aria-label)', $unit))->toBe('Table T1, '.$capacity.' seats, Main dining')
        ->and($xpath->evaluate('string(.//span[@class="lm-floor-table__top"])', $unit))->toBe('T1')
        ->and($xpath->query('.//*[@data-chair]//input | .//*[@data-chair]//button', $unit)->length)->toBe(0);
})->with([2, 3, 4, 6, 8, 10]);

test('configured demo daily hours offer thirteen half-hour starts every day of the week', function (string $date) {
    $this->travelTo('2026-10-04 07:00:00');

    $this->getJson(route('reservation.slots', ['date' => $date]))->assertExactJson([
        'slots' => ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00',
            '11:30', '12:00', '12:30', '13:00', '13:30', '14:00'],
    ]);
})->with(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11']);

test('daily hours reject starts before opening or too late for the full visit', function (string $type, string $time) {
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $data = [...redesignBooking($table, $type), 'reservation_time' => $time];
    if ($type === 'table_and_food') {
        $item = MenuItem::factory()->create();
        $data['food_items'] = [['menu_item_id' => $item->id, 'quantity' => 1]];
    }

    $this->get(route('reservation.availability', $data))->assertSessionHasErrors('reservation_time');
    $this->post(route('reservation.store'), $data)->assertSessionHasErrors('reservation_time');
    $this->assertDatabaseCount('reservations', 0);
    Mail::assertNothingSent();
})->with(['table_only', 'table_and_food'])->with(['07:30', '14:30', '16:00']);

test('the final daily start ends at closing time', function () {
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);

    $this->post(route('reservation.store'), [...redesignBooking($table), 'reservation_time' => '14:00'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(Reservation::firstOrFail()->end_date_time->format('H:i'))->toBe('16:00');
});

/** @return array<string, mixed> */
function redesignBooking(Table $table, string $type = 'table_only'): array
{
    return [
        'reservation_type' => $type, 'table_id' => $table->id,
        'reservation_date' => now()->addDay()->toDateString(), 'reservation_time' => '11:30',
        'guest_count' => 2, 'customer_name' => 'Review Guest',
        'customer_email' => 'review@example.com', 'customer_phone' => '0123456789',
    ];
}

test('both levels reject off-boundary start times at availability and final submission', function (string $type, string $time) {
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $data = [...redesignBooking($table, $type), 'reservation_time' => $time];
    if ($type === 'table_and_food') {
        $item = MenuItem::factory()->create();
        $data['food_items'] = [['menu_item_id' => $item->id, 'quantity' => 1]];
    }

    $this->get(route('reservation.availability', $data))->assertSessionHasErrors('reservation_time');
    $this->post(route('reservation.store'), $data)->assertSessionHasErrors('reservation_time');
    $this->assertDatabaseCount('reservations', 0);
    Mail::assertNothingSent();
})->with(['table_only', 'table_and_food'])->with(['11:15', '11:45', '11:10']);

test('both half-hour boundaries persist a full two-hour reservation', function (string $time) {
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $response = $this->post(route('reservation.store'), [...redesignBooking($table), 'reservation_time' => $time]);
    $reservation = Reservation::firstOrFail();

    $response->assertRedirect();
    expect($reservation->duration_minutes)->toBe(120);
    expect($reservation->reservation_date_time->diffInMinutes($reservation->end_date_time))->toBe(120.0);
    $this->assertDatabaseCount('payments', 0);
    Mail::assertSent(ReservationConfirmationMail::class, 1);
})->with(['11:00', '11:30']);

test('configured weekly hours generate only future slots fitting the full duration', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:10:00', 'Asia/Kuala_Lumpur'));
    config(['restaurant.opening_hours' => [3 => ['open' => '17:15', 'close' => '22:00']]]);

    $this->getJson(route('reservation.slots', ['date' => '2026-10-07']))
        ->assertExactJson(['slots' => ['18:30', '19:00', '19:30', '20:00']]);
    $this->getJson(route('reservation.slots', ['date' => '2026-10-08']))
        ->assertExactJson(['slots' => []]);
});

test('missing hours do not invent a restaurant opening schedule', function () {
    config(['restaurant.opening_hours' => null]);
    expect(app(ReservationSlots::class)->forDate(now()->addDay()->toDateString()))->toBeNull();
});

test('configured closing and past same-day times are enforced on final submission', function (string $time) {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:10:00', 'Asia/Kuala_Lumpur'));
    config(['restaurant.opening_hours' => [3 => ['open' => '17:00', 'close' => '22:00']]]);
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);

    $this->post(route('reservation.store'), [...redesignBooking($table), 'reservation_date' => '2026-10-07', 'reservation_time' => $time])
        ->assertSessionHasErrors('reservation_time');
    $this->assertDatabaseCount('reservations', 0);
})->with(['18:00', '20:30']);

test('adjacent windows are allowed while an intervening half-hour overlaps', function () {
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $data = redesignBooking($table);
    $this->post(route('reservation.store'), $data)->assertRedirect();
    $this->post(route('reservation.store'), [...$data, 'reservation_time' => '12:00'])->assertSessionHasErrors('table_id');
    $this->post(route('reservation.store'), [...$data, 'reservation_time' => '13:30'])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('reservations', 2);
});

test('floor plan hides held tables without shifting the remaining table coordinates', function (string $type) {
    $this->freezeTime();
    $first = Table::factory()->create(['table_number' => 'T1', 'capacity' => 4, 'status' => 'available']);
    $second = Table::factory()->create(['table_number' => 'T2', 'capacity' => 4, 'status' => 'available']);
    $data = redesignBooking($first, $type);
    $before = $this->get(route('reservation.availability', $data))->assertOk();
    $dom = new DOMDocument;
    $dom->loadHTML($before->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $position = (new DOMXPath($dom))->evaluate('string(//label[@data-floor-table="'.$second->id.'"]/@style)');
    Reservation::factory()->create([
        'table_id' => $first->id, 'reservation_type' => 'table_and_food', 'status' => 'pending',
        'reservation_date' => $data['reservation_date'], 'reservation_time' => '11:30', 'created_at' => now(),
    ]);

    $during = $this->get(route('reservation.availability', $data))->assertOk();
    $dom->loadHTML($during->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//input[@name="table_id" and @value="'.$first->id.'"]')->length)->toBe(0);
    expect($xpath->evaluate('string(//label[@data-floor-table="'.$second->id.'"]/@style)'))->toBe($position);

    $this->travel(16)->minutes();
    $this->get(route('reservation.availability', $data))->assertViewHas('availableTables', fn ($tables) => $tables->pluck('id')->all() === [$first->id, $second->id]);
})->with(['table_only', 'table_and_food']);

test('review is read only and shows the canonical level with backend food prices', function (string $type) {
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $data = redesignBooking($table, $type);
    if ($type === 'table_and_food') {
        $item = MenuItem::factory()->create(['name' => 'REVIEW-FOOD-FIXTURE', 'price' => 12.50]);
        $data['food_items'] = [['menu_item_id' => $item->id, 'quantity' => 2, 'unit_price' => 0.01]];
        $data['total'] = 0.02;
    }

    $response = $this->post(route('reservation.review'), $data)->assertOk()
        ->assertSee('Review your reservation')->assertSee($table->table_number)->assertSee('13:30')
        ->assertSee('review@example.com')->assertSee('Review Guest');
    if ($type === 'table_and_food') {
        $response->assertSee('Food total: RM 25.00')->assertSee('2 × RM 12.50')->assertSee('Continue to Payment');
    } else {
        $response->assertSee('Reservation Only')->assertDontSee('Food total')->assertDontSee('Continue to Payment');
    }
    $this->assertDatabaseCount('reservations', 0);
    $this->assertDatabaseCount('payments', 0);
    Mail::assertNothingSent();
})->with(['table_only', 'table_and_food']);

test('a stale reviewed table is rejected and returns to editable availability', function () {
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $data = redesignBooking($table);
    $this->post(route('reservation.review'), $data)->assertOk();
    $table->update(['status' => 'maintenance']);

    $this->from(route('reservation.review'))->post(route('reservation.store'), $data)
        ->assertRedirect(route('reservation.review'))->assertSessionHasErrors('table_id');
    $recovery = $this->get(route('reservation.review'))->assertRedirect();
    $this->get($recovery->headers->get('Location'))->assertOk()->assertSee('No suitable tables are available for this time.');
    $this->assertDatabaseCount('reservations', 0);
});

test('review rejects unavailable food instead of quoting it', function () {
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $item = MenuItem::factory()->unavailable()->create();

    $this->post(route('reservation.review'), [
        ...redesignBooking($table, 'table_and_food'),
        'food_items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('food_items.0.menu_item_id');
    $this->assertDatabaseCount('reservations', 0);
});
