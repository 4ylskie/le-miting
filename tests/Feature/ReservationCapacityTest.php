<?php

use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function capacityBooking(Table $table, int $guests, string $type = 'table_only'): array
{
    return [
        'reservation_type' => $type, 'table_id' => $table->id,
        'reservation_date' => now()->addDay()->toDateString(), 'reservation_time' => '11:30',
        'guest_count' => $guests, 'customer_name' => 'Capacity Guest',
        'customer_email' => 'capacity@example.com', 'customer_phone' => '0123456789',
    ];
}

test('both levels enforce the approved capacity bands at display review and final creation', function (string $type, int $capacity, int $guests, bool $allowed) {
    Mail::fake();
    $table = Table::factory()->create(['table_number' => $capacity === 3 ? 'T11' : 'T1', 'capacity' => $capacity, 'status' => 'available']);
    $data = capacityBooking($table, $guests, $type);
    if ($type === 'table_and_food') {
        $item = MenuItem::factory()->create(['price' => 12]);
        $data['food_items'] = [['menu_item_id' => $item->id, 'quantity' => 2]];
    }

    $this->get(route('reservation.availability', $data))->assertViewHas('availableTables', fn ($tables) => $tables->pluck('id')->all() === ($allowed ? [$table->id] : []));
    if (! $allowed) {
        $this->post(route('reservation.review'), $data)->assertSessionHasErrors('table_id');
        $this->post(route('reservation.store'), $data)->assertSessionHasErrors($guests > $capacity ? 'guest_count' : 'table_id');
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('payments', 0);
        Mail::assertNothingSent();

        return;
    }

    $this->post(route('reservation.review'), $data)->assertOk();
    $this->post(route('reservation.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $reservation = Reservation::firstOrFail();
    expect($reservation->reservation_type)->toBe($type)
        ->and($reservation->duration_minutes)->toBe(120)
        ->and($reservation->status)->toBe($type === 'table_only' ? 'confirmed' : 'pending');
    if ($type === 'table_only') {
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('reservation_foods', 0);
        Mail::assertSent(ReservationConfirmationMail::class, 1);
    } else {
        expect((float) Payment::firstOrFail()->amount)->toBe(24.0);
        Mail::assertNothingSent();
    }
})->with(['table_only', 'table_and_food'])->with([
    [2, 1, true], [2, 2, true], [2, 3, false],
    [3, 1, true], [3, 2, true], [3, 3, true], [3, 4, false],
    [4, 1, true], [4, 4, true], [4, 5, false],
    [6, 1, false], [6, 2, false], [6, 3, true], [6, 6, true], [6, 7, false],
    [8, 1, false], [8, 3, false], [8, 4, false], [8, 5, true], [8, 8, true], [8, 9, false],
    [10, 1, false], [10, 3, false], [10, 4, false], [10, 6, false], [10, 7, true], [10, 10, true], [10, 11, false],
]);

test('booked smaller tables never promote an unsuitable capacity into the floor plan', function (string $type) {
    $small = Table::factory()->create(['table_number' => 'T1', 'capacity' => 4]);
    $suitable = Table::factory()->create(['table_number' => 'T6', 'capacity' => 6]);
    $large = Table::factory()->create(['table_number' => 'T10', 'capacity' => 10]);
    $data = capacityBooking($small, 3, $type);
    Reservation::factory()->confirmed()->create(['table_id' => $small->id, 'reservation_date' => $data['reservation_date'], 'reservation_time' => '11:00']);

    $this->get(route('reservation.availability', $data))
        ->assertViewHas('availableTables', fn ($tables) => $tables->pluck('id')->all() === [$suitable->id])
        ->assertDontSee('data-floor-table="'.$large->id.'"', false);
})->with(['table_only', 'table_and_food']);

test('T11 obeys status full-window overlap and hold expiry rules', function (string $type, string $case, bool $available) {
    Mail::fake();
    $this->freezeTime();
    $table = Table::factory()->create(['table_number' => 'T11', 'capacity' => 3, 'status' => $case === 'maintenance' ? 'maintenance' : 'available']);
    $data = capacityBooking($table, 3, $type);
    if ($case !== 'maintenance') {
        Reservation::factory()->create([
            'table_id' => $table->id, 'reservation_date' => $data['reservation_date'],
            'reservation_time' => $case === 'adjacent' ? '09:30' : '10:00',
            'duration_minutes' => 120,
            'status' => in_array($case, ['active hold', 'expired hold']) ? 'pending' : 'confirmed',
            'created_at' => now()->subMinutes($case === 'expired hold' ? 16 : 1),
        ]);
    }

    $this->get(route('reservation.availability', $data))
        ->assertViewHas('availableTables', fn ($tables) => $tables->isNotEmpty() === $available);
    if (! $available) {
        if ($type === 'table_and_food') {
            $item = MenuItem::factory()->create();
            $data['food_items'] = [['menu_item_id' => $item->id, 'quantity' => 1]];
        }
        $this->post(route('reservation.store'), $data)->assertSessionHasErrors('table_id');
        $this->assertDatabaseCount('reservations', $case === 'maintenance' ? 0 : 1);
        $this->assertDatabaseCount('payments', 0);
        Mail::assertNothingSent();
    }
})->with(['table_only', 'table_and_food'])->with([
    ['maintenance', false], ['overlap', false], ['active hold', false], ['expired hold', true], ['adjacent', true],
]);

test('T11 keeps its three-seat drawing and fixed position across party changes', function () {
    $table = Table::factory()->create(['table_number' => 'T11', 'capacity' => 3]);
    $position = null;
    foreach ([1, 2, 3] as $guests) {
        $response = $this->get(route('reservation.availability', capacityBooking($table, $guests)))->assertOk();
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $label = $xpath->query('//label[@data-floor-table="'.$table->id.'"]')->item(0);
        expect($xpath->query('.//*[@data-chair]', $label)->length)->toBe(3);
        expect($xpath->evaluate('string(.//input/@aria-label)', $label))->toBe('Table T11, 3 seats, Veranda');
        $coordinates = [$label->getAttribute('data-row'), $label->getAttribute('data-column')];
        $position ??= $coordinates;
        expect($coordinates)->toBe($position)->toBe(['1', '3']);
    }
});

test('unsupported capacities are excluded and cannot be booked by crafted submissions', function () {
    $table = Table::factory()->create(['capacity' => 12]);
    $data = capacityBooking($table, 10);
    $this->get(route('reservation.availability', $data))->assertViewHas('availableTables', fn ($tables) => $tables->isEmpty());
    $this->post(route('reservation.store'), $data)->assertSessionHasErrors('table_id');
    $this->assertDatabaseCount('reservations', 0);
});

test('changed visit criteria clear flashed table selections on the server', function (string $field, mixed $value) {
    $table = Table::factory()->create(['capacity' => 4]);
    $old = capacityBooking($table, 2);
    $changed = [...$old, $field => $value ?? now()->addDays(2)->toDateString()];

    $response = $this->withSession(['_old_input' => $old])->get(route('reservation.availability', $changed))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    expect((new DOMXPath($document))->query('//input[@name="table_id" and @checked]')->length)->toBe(0);
})->with([['guest_count', 3], ['reservation_date', null], ['reservation_time', '12:00'], ['reservation_type', 'table_and_food']]);

test('oversized parties are distinguished from occupied suitable tables', function () {
    $table = Table::factory()->create(['capacity' => 10]);
    $this->get(route('reservation.availability', capacityBooking($table, 12)))
        ->assertSee("We don't have a table large enough for this party size.", false);
    Reservation::factory()->confirmed()->create(['table_id' => $table->id, 'reservation_date' => now()->addDay()->toDateString(), 'reservation_time' => '11:00']);
    $this->get(route('reservation.availability', capacityBooking($table, 8)))
        ->assertSee('No suitable tables are available for this time.');
});
