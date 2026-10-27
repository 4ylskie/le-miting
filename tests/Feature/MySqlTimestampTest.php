<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\NonTransactionalDatabase;

uses(NonTransactionalDatabase::class);

test('MySQL timestamp and datetime round trip as UTC without double conversion', function () {
    $instant = CarbonImmutable::parse('2026-10-09 16:00:00', 'UTC');
    $this->travelTo($instant);
    expect(config('app.timezone'))->toBe('UTC');
    expect(DB::scalar('SELECT @@session.time_zone'))->toBe('+00:00');
    expect(app(BusinessTime::class)->today())->toBe('2026-10-10');

    try {
        Schema::create('timezone_round_trip_probes', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('event_timestamp');
            $table->dateTime('event_datetime');
        });
        DB::table('timezone_round_trip_probes')->insert([
            'event_timestamp' => '2026-10-09 16:00:00',
            'event_datetime' => '2026-10-09 16:00:00',
        ]);
        $stored = DB::table('timezone_round_trip_probes')->first();
        expect($stored->event_timestamp)->toBe('2026-10-09 16:00:00');
        expect($stored->event_datetime)->toBe('2026-10-09 16:00:00');
        expect((int) DB::scalar('SELECT UNIX_TIMESTAMP(event_timestamp) FROM timezone_round_trip_probes'))->toBe($instant->getTimestamp());
    } finally {
        Schema::dropIfExists('timezone_round_trip_probes');
    }
});

test('MySQL payment and check in timestamps preserve the Malaysian appointment instant', function () {
    $instant = CarbonImmutable::parse('2026-10-09 16:00:00', 'UTC');
    $this->travelTo($instant);
    $order = Order::factory()->create();
    $payment = Payment::factory()->paid()->create(['order_id' => $order->id, 'paid_at' => $instant]);
    $reservation = Reservation::factory()->confirmed()->create([
        'reservation_date' => '2026-10-10', 'reservation_time' => '08:00:00',
        'checked_in_at' => $instant,
    ]);

    expect($payment->fresh()->paid_at->getTimestamp())->toBe($instant->getTimestamp());
    expect($reservation->fresh()->checked_in_at->getTimestamp())->toBe($instant->getTimestamp());
    expect(DB::table('payments')->where('id', $payment->id)->value('paid_at'))->toBe('2026-10-09 16:00:00');
    expect($reservation->fresh()->reservation_date_time->utc()->toDateTimeString())->toBe('2026-10-10 00:00:00');
});
