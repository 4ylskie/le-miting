<?php

use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\Payments\MockPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function reservationXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

/** @return array<string, string|int> */
function reservationAvailabilityInput(string $type = 'table_only'): array
{
    return [
        'reservation_type' => $type,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '11:00',
        'guest_count' => 4,
    ];
}

test('reservation entry explains both existing guest paths', function () {
    $response = $this->get(route('reservation.create'))->assertOk()
        ->assertSee('Reservation Only')->assertSee('Reservation + Pre-Order')->assertSee('No account needed.');
    $xpath = reservationXPath($response->getContent());

    foreach (['reservation.table', 'reservation.table-food'] as $route) {
        expect($xpath->query('//main//a[@href="'.route($route).'"]')->length)->toBe(1);
    }
    expect($xpath->query('//main//img')->length)->toBe(0);
});

test('each reservation entry form preserves its availability fields and constraints', function (string $route, string $type) {
    $response = $this->get(route($route))->assertOk();
    $xpath = reservationXPath($response->getContent());
    $form = $xpath->query('//main//form')->item(0);

    expect($form->getAttribute('method'))->toBe('GET')
        ->and($form->getAttribute('action'))->toBe(route('reservation.availability'))
        ->and($xpath->evaluate('string(.//input[@name="reservation_type"]/@value)', $form))->toBe($type);
    foreach (['reservation_date' => 'date', 'guest_count' => 'number'] as $field => $inputType) {
        expect($xpath->query('.//label[@for="'.$field.'"]', $form)->length)->toBe(1)
            ->and($xpath->query('.//input[@name="'.$field.'" and @type="'.$inputType.'" and @required]', $form)->length)->toBe(1);
    }
    expect($xpath->query('//select[@name="reservation_time" and @required]')->length)->toBe(1)
        ->and($xpath->query('//label[@for="reservation_time"]')->length)->toBe(1)
        ->and($xpath->query('//form[@action="'.route('reservation.store').'"]')->length)->toBe(0);
})->with([
    ['reservation.table', 'table_only'],
    ['reservation.table-food', 'table_and_food'],
]);

test('availability renders only backend results and reflects stored table capacity', function () {
    $available = Table::factory()->create(['table_number' => 'ELIGIBLE-REFERENCE', 'capacity' => 4, 'status' => 'available']);
    $small = Table::factory()->create(['table_number' => 'SMALL-REFERENCE', 'capacity' => 2, 'status' => 'available']);
    $maintenance = Table::factory()->create(['table_number' => 'MAINTENANCE-REFERENCE', 'status' => 'maintenance']);
    $booked = Table::factory()->create(['table_number' => 'BOOKED-REFERENCE', 'capacity' => 4, 'status' => 'available']);
    Reservation::factory()->confirmed()->create([
        'table_id' => $booked->id,
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '11:00',
    ]);

    $response = $this->get(route('reservation.availability', reservationAvailabilityInput()))->assertOk()
        ->assertSee($available->table_number)->assertSee('Seats up to 4')->assertDontSee($small->table_number)
        ->assertDontSee($maintenance->table_number)->assertDontSee($booked->table_number);
    $xpath = reservationXPath($response->getContent());
    expect($xpath->query('//input[@name="table_id"]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="table_id" and @value="'.$available->id.'" and not(@disabled)]')->length)->toBe(1)
        ->and($xpath->query('//input[@name="table_id" and @value="'.$small->id.'"]')->length)->toBe(0)
        ->and($xpath->query('//input[@name="table_id"]/ancestor::fieldset/legend')->length)->toBe(1);
    $form = $xpath->query('//form[@id="reservation-form"]')->item(0);
    expect($form->getAttribute('method'))->toBe('POST')
        ->and($form->getAttribute('action'))->toBe(route('reservation.review'))
        ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    foreach (reservationAvailabilityInput() as $field => $value) {
        expect($xpath->evaluate('string(.//input[@name="'.$field.'"]/@value)', $form))->toBe((string) $value);
    }
    expect($xpath->query('//*[@id="food-selection"]')->length)->toBe(0)
        ->and($xpath->evaluate('normalize-space(//*[@id="reservation-submit"])'))->toBe('Review Reservation');
});

test('empty availability offers recovery without a booking action', function (string $type) {
    $response = $this->get(route('reservation.availability', reservationAvailabilityInput($type)))->assertOk()
        ->assertSee('No supported tables suit this party size.');
    $xpath = reservationXPath($response->getContent());
    expect($xpath->query('//form[@action="'.route('reservation.availability').'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="reservation-submit" or @id="food-total"]')->length)->toBe(0);
})->with(['table_only', 'table_and_food']);

test('a group without a sufficiently large table cannot submit the displayed form', function () {
    Table::factory()->create(['capacity' => 2, 'status' => 'available']);
    $response = $this->get(route('reservation.availability', reservationAvailabilityInput()))->assertOk()
        ->assertSee("We don't have a table large enough for this party size.", false);
    expect(reservationXPath($response->getContent())->query('//button[@id="reservation-submit"]')->length)->toBe(0);
});

test('reservation food reuses menu media and retains isolated selection controls', function () {
    Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $pictured = MenuItem::factory()->create(['name' => 'PICTURED-PREORDER-FIXTURE', 'description' => 'First test dish.', 'price' => 12.50, 'image_path' => 'menu/preorder.jpg']);
    $plain = MenuItem::factory()->create(['name' => 'TEXT-PREORDER-FIXTURE', 'description' => 'Second test dish.', 'image_path' => null]);
    $unavailable = MenuItem::factory()->unavailable()->create(['name' => 'UNAVAILABLE-PREORDER-FIXTURE']);
    $orderItems = [$plain->id => ['menu_item_id' => $plain->id, 'quantity' => 3]];
    $reservationItems = [$pictured->id => ['menu_item_id' => $pictured->id, 'quantity' => 2]];

    $response = $this->withSession(['cart.order.items' => $orderItems, 'cart.reservation.items' => $reservationItems])
        ->get(route('reservation.availability', reservationAvailabilityInput('table_and_food')))->assertOk()
        ->assertSee($pictured->name)->assertSee($plain->name)->assertSee('RM 12.50')->assertDontSee($unavailable->name)
        ->assertSee('Review Reservation')->assertSee('15-minute payment hold')
        ->assertSessionHas('cart.order.items', $orderItems)->assertSessionHas('cart.reservation.items', $reservationItems);
    $xpath = reservationXPath($response->getContent());
    expect($xpath->query('//form//form')->length)->toBe(0)
        ->and($xpath->query('//main//form[@action="'.route('cart.add').'"]')->length)->toBe(0)
        ->and($xpath->query('//main//figure')->length)->toBe(1)
        ->and($xpath->evaluate('string(//main//img/@src)'))->toBe(asset('storage/'.$pictured->image_path))
        ->and($xpath->evaluate('string(//main//img/@alt)'))->toBe($pictured->name)
        ->and($xpath->query('//input[@name="total" or @name="amount" or @name="unit_price"]')->length)->toBe(0);
    foreach ([$pictured, $plain] as $item) {
        expect($xpath->query('//input[@name="food_items['.$item->id.'][menu_item_id]" and @value="'.$item->id.'"]')->length)->toBe(1)
            ->and($xpath->query('//label[@for="food-'.$item->id.'"]')->length)->toBe(1)
            ->and($xpath->query('//select[@name="food_items['.$item->id.'][quantity]" and @disabled]/option')->length)->toBe(10)
            ->and($xpath->query('//label[@for="food-quantity-'.$item->id.'"]')->length)->toBe(1);
    }
});

test('an empty food menu explains why payment cannot continue', function () {
    Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $response = $this->get(route('reservation.availability', reservationAvailabilityInput('table_and_food')))->assertOk()
        ->assertSee('No dishes are available to pre-order.');
    expect(reservationXPath($response->getContent())->query('//button[@id="reservation-submit" and @disabled]')->length)->toBe(1);
});

test('availability validation retains input and associates errors', function () {
    $this->from(route('reservation.table'))->get(route('reservation.availability', [
        'reservation_type' => 'table_only', 'reservation_date' => 'invalid', 'reservation_time' => 'invalid', 'guest_count' => 21,
    ]))->assertSessionHasErrors(['reservation_date', 'reservation_time', 'guest_count']);
    $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('reservation.table'))->assertOk();
    $xpath = reservationXPath($response->getContent());
    foreach (['reservation_date', 'reservation_time', 'guest_count'] as $field) {
        $control = $xpath->query('//*[@id="'.$field.'"]')->item(0);
        expect($control->getAttribute('aria-invalid'))->toBe('true')
            ->and($xpath->query('//*[@id="'.$control->getAttribute('aria-describedby').'"]')->length)->toBe(1);
    }
    expect($xpath->evaluate('string(//*[@id="guest_count"]/@value)'))->toBe('21');
});

test('reservation validation associates contact table and food errors', function () {
    Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $item = MenuItem::factory()->create(['image_path' => null]);
    $availability = route('reservation.availability', reservationAvailabilityInput('table_and_food'));
    $this->from($availability)->post(route('reservation.store'), [
        ...reservationAvailabilityInput('table_and_food'),
        'customer_name' => '', 'customer_email' => 'invalid-email', 'customer_phone' => '',
        'special_request' => str_repeat('x', 1001),
        'food_items' => [$item->id => ['menu_item_id' => $item->id, 'quantity' => 11]],
    ])->assertSessionHasErrors(['table_id', 'customer_name', 'customer_email', 'customer_phone', 'special_request', 'food_items.'.$item->id.'.quantity']);
    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($availability)->assertOk();
    $xpath = reservationXPath($response->getContent());
    foreach (['customer_name', 'customer_email', 'customer_phone', 'special_request', 'food-quantity-'.$item->id] as $field) {
        $control = $xpath->query('//*[@id="'.$field.'"]')->item(0);
        expect($control->getAttribute('aria-invalid'))->toBe('true')
            ->and($xpath->query('//*[@id="'.$control->getAttribute('aria-describedby').'"]')->length)->toBe(1);
    }
    expect($xpath->query('//input[@name="table_id" and @aria-invalid="true" and @aria-describedby="table_id-error"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="table_id-error"]')->length)->toBe(1);
});

test('table only submission still confirms without food or payment and retains both carts', function () {
    Mail::fake();
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $carts = ['order' => ['items' => [10 => ['menu_item_id' => 10, 'quantity' => 1]]], 'reservation' => ['items' => [20 => ['menu_item_id' => 20, 'quantity' => 2]]]];
    $response = $this->withSession(['cart' => $carts])->post(route('reservation.store'), [
        ...reservationAvailabilityInput(), 'table_id' => $table->id,
        'customer_name' => 'Aida', 'customer_email' => 'aida@example.com', 'customer_phone' => '0123456789',
    ])->assertRedirect()->assertSessionHas('cart', $carts);
    $reservation = Reservation::firstOrFail();
    expect($reservation->status)->toBe('confirmed');
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('reservation_foods', 0);
    Mail::assertSent(ReservationConfirmationMail::class, 1);
    $this->get($response->headers->get('Location'))->assertOk()->assertSee($reservation->reservation_number)
        ->assertSee('Table Only')->assertDontSee('Food total')->assertDontSee('Payment status:');
});

test('paid reservation confirmation presents purchase snapshots and actual payment state', function () {
    $reservation = Reservation::factory()->withFood()->confirmed()->create(['reservation_number' => 'RES-CONFIRMATION-REFERENCE']);
    $item = MenuItem::factory()->create(['name' => 'CURRENT-RENAMED-DISH', 'price' => 99]);
    $reservation->reservationFoods()->create(['menu_item_id' => $item->id, 'item_name' => 'PURCHASED-DISH-SNAPSHOT', 'unit_price' => 12.50, 'quantity' => 2]);
    Payment::factory()->forReservation($reservation)->paid()->create();
    $url = URL::temporarySignedRoute('reservation.confirmation', now()->addHours(24), ['reservation' => $reservation]);

    $this->get($url)->assertOk()->assertSee($reservation->reservation_number)->assertSee($reservation->customer_email)
        ->assertSee('Table + Food')->assertSee('2 × PURCHASED-DISH-SNAPSHOT')->assertSee('RM 25.00')
        ->assertSee('Payment status: Paid')->assertDontSee('CURRENT-RENAMED-DISH')->assertDontSee('paid with FIUU')
        ->assertDontSee('has been sent');
});

test('table and food payment preserves the order cart and clears only the paid reservation cart', function () {
    Mail::fake();
    config(['payment.driver' => 'mock']);
    $table = Table::factory()->create(['capacity' => 4, 'status' => 'available']);
    $item = MenuItem::factory()->create(['price' => 12.50]);
    $orderItems = [$item->id => ['menu_item_id' => $item->id, 'quantity' => 5]];
    $foodItems = [$item->id => ['menu_item_id' => $item->id, 'quantity' => 2]];
    $response = $this->withSession(['cart.order.items' => $orderItems])->post(route('reservation.store'), [
        ...reservationAvailabilityInput('table_and_food'), 'table_id' => $table->id,
        'customer_name' => 'Aida', 'customer_email' => 'aida@example.com', 'customer_phone' => '0123456789',
        'food_items' => $foodItems,
    ])->assertSessionHas('cart.order.items', $orderItems)->assertSessionHas('cart.reservation.items', $foodItems);
    $reservation = Reservation::firstOrFail();
    $payment = $reservation->payment;
    $response->assertRedirect(route('payment.create', $payment));
    expect($reservation->status)->toBe('pending')
        ->and((float) $payment->amount)->toBe(25.0);
    Mail::assertNotSent(ReservationConfirmationMail::class);

    $paidResponse = $this->post(route('payment.callback'), app(MockPaymentService::class)->callbackPayload($payment, 'success'))
        ->assertRedirect()->assertSessionHas('cart.order.items', $orderItems)->assertSessionMissing('cart.reservation');
    expect($reservation->refresh()->status)->toBe('confirmed');
    Mail::assertSent(ReservationConfirmationMail::class, 1);
    $this->get($paidResponse->headers->get('Location'))->assertOk()->assertSee($reservation->reservation_number)
        ->assertSee('RM 25.00')->assertSee('Payment status: Paid');
});

test('cancellation form preserves its signed action and distinguishes paid food', function (bool $paid) {
    Mail::fake();
    $reservation = Reservation::factory()->create(['reservation_type' => $paid ? 'table_and_food' : 'table_only', 'status' => $paid ? 'confirmed' : 'pending']);
    if ($paid) {
        Payment::factory()->forReservation($reservation)->paid()->create();
    }
    $url = URL::temporarySignedRoute('reservation.cancel-form', now()->addHours(24), ['reservation' => $reservation]);
    $response = $this->get($url)->assertOk()->assertSee($reservation->reservation_number);
    $xpath = reservationXPath($response->getContent());
    $form = $xpath->query('//main//form')->item(0);
    expect($form->getAttribute('method'))->toBe('POST')
        ->and($form->getAttribute('action'))->toBe($url)
        ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    if ($paid) {
        $response->assertSee('this cancellation does not issue a refund.');
    } else {
        expect($xpath->evaluate('string(//main)'))->not->toContain('refund');
    }
    $this->post($form->getAttribute('action'))->assertOk()->assertSee('Reservation Cancelled')->assertSee($reservation->reservation_number);
    expect($reservation->refresh()->status)->toBe('cancelled');
    if ($paid) {
        expect($reservation->payment->status)->toBe('paid');
    }
})->with([true, false]);

test('ineligible cancellation displays the existing record without a form', function (string $status) {
    $reservation = Reservation::factory()->create([
        'status' => $status,
        'reservation_date' => $status === 'confirmed' ? now()->subDay()->toDateString() : now()->addDay()->toDateString(),
    ]);
    $url = URL::temporarySignedRoute('reservation.cancel-form', now()->addHours(24), ['reservation' => $reservation]);
    $response = $this->get($url)->assertOk()->assertSee($reservation->reservation_number);
    expect(reservationXPath($response->getContent())->query('//main//form')->length)->toBe(0);
    expect($reservation->refresh()->status)->toBe($status);
})->with(['completed', 'cancelled', 'confirmed']);

test('invalid reservation links preserve the shared error response and safe navigation', function (string $route) {
    $reservation = Reservation::factory()->confirmed()->create();
    $url = URL::temporarySignedRoute($route, now()->subMinute(), ['reservation' => $reservation]);
    $response = $this->get($url)->assertForbidden()->assertViewIs('errors.invalid-link')
        ->assertSee('This link is no longer valid.')->assertDontSee($reservation->reservation_number);
    $xpath = reservationXPath($response->getContent());
    expect($xpath->query('//main//a[@href="'.route('home').'"]')->length)->toBe(1)
        ->and($xpath->query('//main//h1')->length)->toBe(1);
})->with(['reservation.confirmation', 'reservation.cancel-form', 'reservation.receipt']);
