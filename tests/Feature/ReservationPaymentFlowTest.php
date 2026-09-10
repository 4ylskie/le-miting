<?php

use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a table_and_food reservation with selected dishes creates a pending payment and routes to the gateway', function () {
    $this->seed(DatabaseSeeder::class);

    $table = Table::where('table_number', 'T1')->firstOrFail();
    $item = MenuItem::where('slug', 'satay-skewers')->firstOrFail();

    $response = $this->post(route('reservation.store'), [
        'reservation_type' => 'table_and_food',
        'table_id' => $table->id,
        'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
        'reservation_time' => '19:00',
        'guest_count' => 2,
        'customer_name' => 'Aida',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
        'food_items' => [
            $item->id => ['menu_item_id' => $item->id, 'quantity' => 2],
        ],
    ]);

    $reservation = Reservation::firstOrFail();

    $response->assertRedirect(route('payment.create', $reservation->payment));

    expect($reservation->reservation_type)->toBe('table_and_food');
    expect($reservation->status)->toBe('pending');

    $payment = $reservation->payment;

    expect($payment->status)->toBe('pending');
    expect((float) $payment->amount)->toBe((float) ($item->price * 2));
    expect($reservation->reservationFoods)->toHaveCount(1);
    expect($reservation->reservationFoods->first()->quantity)->toBe(2);
});

test('the food selection form submits the selected dish id', function () {
    $this->seed(DatabaseSeeder::class);

    $item = MenuItem::where('slug', 'satay-skewers')->firstOrFail();

    $this->get(route('reservation.availability', [
        'reservation_type' => 'table_and_food',
        'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
        'reservation_time' => '19:00',
        'guest_count' => 2,
    ]))
        ->assertOk()
        ->assertSee('name="food_items['.$item->id.'][menu_item_id]"', false);
});

test('submitting food quantities without the dish id is rejected', function () {
    $this->seed(DatabaseSeeder::class);

    $table = Table::where('table_number', 'T1')->firstOrFail();
    $item = MenuItem::where('slug', 'satay-skewers')->firstOrFail();

    $this->post(route('reservation.store'), [
        'reservation_type' => 'table_and_food',
        'table_id' => $table->id,
        'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
        'reservation_time' => '19:00',
        'guest_count' => 2,
        'customer_name' => 'Aida',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
        'food_items' => [
            $item->id => ['quantity' => 2],
        ],
    ])
        ->assertSessionHasErrors('food_items.*.menu_item_id');

    expect(Reservation::count())->toBe(0);
    expect(Payment::count())->toBe(0);
});

test('the availability page carries the booking details into the store form', function () {
    $this->seed(DatabaseSeeder::class);

    $date = Carbon::tomorrow()->format('Y-m-d');

    $this->get(route('reservation.availability', [
        'reservation_type' => 'table_and_food',
        'reservation_date' => $date,
        'reservation_time' => '19:00',
        'guest_count' => 2,
    ]))
        ->assertOk()
        ->assertSee('name="reservation_date" value="'.$date.'"', false)
        ->assertSee('name="reservation_time" value="19:00"', false)
        ->assertSee('name="guest_count" value="2"', false);
});

test('continue to payment completes a table_and_food reservation through the mock gateway', function () {
    $this->seed(DatabaseSeeder::class);
    config()->set('payment.driver', 'mock');
    config()->set('app.url', 'http://localhost');

    $table = Table::where('table_number', 'T1')->firstOrFail();
    $item = MenuItem::where('slug', 'satay-skewers')->firstOrFail();

    $storeResponse = $this->post(route('reservation.store'), [
        'reservation_type' => 'table_and_food',
        'table_id' => $table->id,
        'reservation_date' => Carbon::tomorrow()->format('Y-m-d'),
        'reservation_time' => '19:00',
        'guest_count' => 2,
        'customer_name' => 'Aida',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
        'food_items' => [
            $item->id => ['menu_item_id' => $item->id, 'quantity' => 2],
        ],
    ]);

    $payment = Payment::firstOrFail();

    $storeResponse->assertRedirect(route('payment.create', $payment));

    $gatewayResponse = $this->get(route('payment.create', $payment));
    $gatewayResponse->assertRedirect(route('payment.gateway', $payment));

    $this->get(route('payment.gateway', $payment))
        ->assertOk()
        ->assertSee($payment->provider_reference);

    $amount = number_format((float) $payment->amount, 2, '.', '');
    $secret = (string) config('payment.fiuu.secret_key');
    $signature = hash_hmac('sha256', $secret.$payment->provider_reference.$amount.'paid'.'MYR', $secret);

    $this->post(route('payment.callback'), [
        'reference' => $payment->provider_reference,
        'amount' => $amount,
        'status' => 'paid',
        'currency' => 'MYR',
        'signature' => $signature,
    ])->assertRedirect(route('reservation.confirmation', $payment->reservation));

    expect($payment->refresh()->status)->toBe('paid');
    expect($payment->reservation->status)->toBe('confirmed');
});
