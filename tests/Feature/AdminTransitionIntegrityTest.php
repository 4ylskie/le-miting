<?php

use App\Events\ReservationPaymentVerified;
use App\Mail\GoogleReviewInvitationMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\ReservationCancellationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\MockPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $attributes */
function transitionReservation(array $attributes = []): Reservation
{
    $reservation = Reservation::factory()->create([
        'table_id' => Table::factory()->create(['capacity' => 4, 'status' => 'available'])->id,
        'guest_count' => 2, 'reservation_date' => '2026-10-09', 'reservation_time' => '11:00',
        'status' => 'pending', 'duration_minutes' => 120, ...$attributes,
    ]);
    if ($reservation->reservation_type === 'table_and_food') {
        $reservation->reservationFoods()->create([
            'menu_item_id' => MenuItem::factory()->create()->id,
            'item_name' => 'Preorder transition fixture', 'unit_price' => 20, 'quantity' => 2,
        ]);
    }

    return $reservation->refresh();
}

test('orders reject ineligible financial records without changing state or notifying', function (string $type, string $problem) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $order = Order::factory()->create(['order_type' => $type, 'total' => 40]);
    if ($problem !== 'missing') {
        $payment = Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 40]);
        match ($problem) {
            'pending', 'failed', 'refunded' => $payment->update(['status' => $problem]),
            'amount' => $payment->update(['amount' => 39]),
            'currency' => $payment->update(['currency' => 'USD']),
            'timestamp' => $payment->update(['paid_at' => null]),
            'wrong parent' => $payment->update(['order_id' => Order::factory()->create()->id]),
            'ambiguous' => Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 40]),
        };
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.orders.update-status', $order), ['status' => 'confirmed', 'expected_status' => 'pending'])
        ->assertSessionHas('error');
    expect($order->refresh()->status)->toBe('pending');
    Mail::assertNothingSent();
})->with(['dine-in', 'takeaway'])->with(['missing', 'pending', 'failed', 'refunded', 'amount', 'currency', 'timestamp', 'wrong parent', 'ambiguous']);

test('a verified order confirms once and retains financial values', function () {
    Mail::fake();
    $order = Order::factory()->create(['total' => 40]);
    $payment = Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 40]);
    $before = $payment->refresh()->getAttributes();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patch(route('admin.orders.update-status', $order), ['status' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('status');
    $this->patch(route('admin.orders.update-status', $order), ['status' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('error');
    expect($order->refresh()->status)->toBe('confirmed');
    expect($payment->refresh()->getAttributes())->toBe($before);
    Mail::assertSent(OrderConfirmationMail::class, 1);
});

test('historically unpaid orders cannot progress but can be cancelled', function (string $source, string $target) {
    Mail::fake();
    $order = Order::factory()->create(['status' => $source]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patch(route('admin.orders.update-status', $order), ['status' => $target, 'expected_status' => $source])->assertSessionHas('error');
    expect($order->refresh()->status)->toBe($source);
    Mail::assertNothingSent();
    $this->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled', 'expected_status' => $source])->assertSessionHas('status');
    expect($order->refresh()->status)->toBe('cancelled');
    Mail::assertNothingSent();
})->with([['confirmed', 'preparing'], ['preparing', 'ready'], ['ready', 'completed']]);

test('reservation confirmation revalidates allocation for both levels', function (string $type, bool $blocked) {
    $this->travelTo('2026-10-09 02:00:00');
    Mail::fake();
    $reservation = transitionReservation(['reservation_type' => $type, 'created_at' => now()->subHour()]);
    $payment = $type === 'table_and_food' ? Payment::factory()->forReservation($reservation)->paid()->create() : null;
    $paymentBefore = $payment?->refresh()->getAttributes();
    if ($blocked) {
        transitionReservation(['table_id' => $reservation->table_id, 'status' => 'completed']);
    }
    $tableId = $reservation->table_id;
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'confirmed', 'expected_status' => 'pending'])
        ->assertSessionHas($blocked ? 'error' : 'status');
    expect($reservation->refresh()->status)->toBe($blocked ? 'pending' : 'confirmed');
    expect($reservation->table_id)->toBe($tableId);
    expect($payment?->refresh()->getAttributes())->toBe($paymentBefore);
    if ($blocked) {
        Mail::assertNothingSent();
    } else {
        Mail::assertSent(ReservationConfirmationMail::class, 1);
    }
})->with(['table_only', 'table_and_food'])->with([false, true]);

test('unpaid level two cannot confirm check in or complete', function (string $action) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $reservation = transitionReservation([
        'reservation_type' => 'table_and_food',
        'status' => $action === 'confirmed' ? 'pending' : 'confirmed',
        'checked_in_at' => $action === 'completed' ? now()->subMinute() : null,
    ]);
    $before = $reservation->getAttributes();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    if ($action === 'check-in') {
        $this->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number])->assertSessionHasErrors('reservation_number');
    } else {
        $this->patch(route('admin.reservations.update-status', $reservation), ['status' => $action, 'expected_status' => $reservation->status])->assertSessionHas('error');
    }
    expect($reservation->refresh()->getAttributes())->toBe($before);
    Mail::assertNothingSent();
})->with(['confirmed', 'check-in', 'completed']);

test('admin forms reject missing invalid and stale expected states even for legal cancellation', function (string $kind, ?string $expected) {
    Mail::fake();
    $parent = $kind === 'orders' ? Order::factory()->create(['status' => 'confirmed']) : transitionReservation(['status' => 'confirmed']);
    $data = ['status' => 'cancelled'];
    if ($expected !== null) {
        $data['expected_status'] = $expected;
    }
    $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.'.$kind.'.update-status', $parent), $data);
    if ($expected === null || $expected === 'invalid') {
        $response->assertSessionHasErrors('expected_status');
    } else {
        $response->assertSessionHas('error');
    }
    expect($parent->refresh()->status)->toBe('confirmed');
    Mail::assertNothingSent();
})->with(['orders', 'reservations'])->with([null, 'invalid', 'pending']);

test('admin cancellation sends once and customer cancellation does not duplicate it', function (bool $customerFirst) {
    $this->travelTo('2026-10-09 02:00:00');
    Mail::fake();
    $reservation = transitionReservation(['status' => 'confirmed']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    if ($customerFirst) {
        $this->post(URL::temporarySignedRoute('reservation.cancel', now()->addHour(), ['reservation' => $reservation]))->assertOk();
    } else {
        $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'cancelled', 'expected_status' => 'confirmed'])->assertSessionHas('status');
    }
    $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'cancelled', 'expected_status' => 'confirmed'])->assertSessionHas('error');
    expect($reservation->refresh()->status)->toBe('cancelled');
    Mail::assertSent(ReservationCancellationMail::class, 1);
    Mail::assertNotSent(GoogleReviewInvitationMail::class);
})->with([false, true]);

test('cancellation email reports only verified payment facts', function (string $type, bool $paid) {
    $this->travelTo('2026-10-09 02:00:00');
    Mail::fake();
    $reservation = transitionReservation(['reservation_type' => $type, 'status' => $paid ? 'confirmed' : 'pending']);
    if ($paid) {
        Payment::factory()->forReservation($reservation)->paid()->create();
    }

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'cancelled', 'expected_status' => $reservation->status])
        ->assertSessionHas('status');

    expect($reservation->refresh()->status)->toBe('cancelled');
    Mail::assertSent(ReservationCancellationMail::class, 1);
    $mail = Mail::sent(ReservationCancellationMail::class)->sole();
    $mail->assertSeeInHtml($reservation->reservation_number);
    $mail->assertSeeInHtml('Your reservation has been cancelled.');
    $mail->assertDontSeeInHtml('A refund for your pre-ordered food will be processed');
    $mail->assertDontSeeInHtml('refund has been processed');
    if ($paid) {
        $mail->assertSeeInHtml('This cancellation does not issue a refund.');
        $mail->assertSeeInHtml('eligibility and processing are handled by the restaurant.');
    } else {
        $mail->assertDontSeeInHtml('refund');
    }
})->with([
    'unpaid table only' => ['table_only', false],
    'unpaid table and food' => ['table_and_food', false],
    'paid table and food' => ['table_and_food', true],
]);

test('repeated signed customer cancellation sends no second email', function () {
    $this->travelTo('2026-10-09 02:00:00');
    Mail::fake();
    $reservation = transitionReservation(['status' => 'confirmed']);
    $url = URL::temporarySignedRoute('reservation.cancel', now()->addHour(), ['reservation' => $reservation]);

    $this->post($url)->assertOk();
    $this->post($url)->assertUnprocessable();

    expect($reservation->refresh()->status)->toBe('cancelled');
    Mail::assertSent(ReservationCancellationMail::class, 1);
});

test('a paid allocation conflict is resolved only by an explicit eligible admin attempt', function () {
    $this->travelTo('2026-10-09 02:00:00');
    Mail::fake();
    $reservation = transitionReservation(['reservation_type' => 'table_and_food', 'created_at' => now()->subHour()]);
    $blocker = transitionReservation(['table_id' => $reservation->table_id, 'status' => 'confirmed']);
    $payment = Payment::factory()->forReservation($reservation)->create();
    $gateway = app(MockPaymentService::class);
    $gateway->processDemoOutcome($payment, 'success');
    expect($payment->refresh()->status)->toBe('paid');
    expect($reservation->refresh()->status)->toBe('pending');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $data = ['status' => 'confirmed', 'expected_status' => 'pending'];
    $this->patch(route('admin.reservations.update-status', $reservation), $data)->assertSessionHas('error');
    Mail::assertNothingSent();
    $blocker->update(['status' => 'cancelled']);
    $gateway->processDemoOutcome($payment, 'success');
    expect($reservation->refresh()->status)->toBe('pending');
    Mail::assertNothingSent();
    $this->patch(route('admin.reservations.update-status', $reservation), $data)->assertSessionHas('status');
    $this->patch(route('admin.reservations.update-status', $reservation), $data)->assertSessionHas('error');
    expect($reservation->refresh()->status)->toBe('confirmed');
    Mail::assertSent(ReservationConfirmationMail::class, 1);
});

test('nested transitions notify only on outer commit and never on rollback', function (string $kind, bool $commit) {
    Mail::fake();
    $parent = $kind === 'orders' ? Order::factory()->create(['total' => 40]) : transitionReservation();
    if ($kind === 'orders') {
        Payment::factory()->paid()->create(['order_id' => $parent->id, 'amount' => 40]);
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    DB::beginTransaction();
    $this->patch(route('admin.'.$kind.'.update-status', $parent), ['status' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('status');
    Mail::assertNothingSent();
    if ($commit) {
        DB::commit();
        Mail::assertSent($kind === 'orders' ? OrderConfirmationMail::class : ReservationConfirmationMail::class, 1);
    } else {
        DB::rollBack();
        expect($parent->refresh()->status)->toBe('pending');
        Mail::assertNothingSent();
    }
})->with(['orders', 'reservations'])->with([false, true]);

test('level one confirmation never emits a payment verification event', function () {
    Mail::fake();
    Event::fake([ReservationPaymentVerified::class]);
    $reservation = transitionReservation();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('status');
    Mail::assertSent(ReservationConfirmationMail::class, 1);
    Event::assertNotDispatched(ReservationPaymentVerified::class);
    $this->assertDatabaseCount('payments', 0);
});

test('historically unpaid level two can only cancel before arrival', function (bool $arrived) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $reservation = transitionReservation(['reservation_type' => 'table_and_food', 'status' => 'confirmed', 'checked_in_at' => $arrived ? now() : null]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'cancelled', 'expected_status' => 'confirmed'])->assertSessionHas($arrived ? 'error' : 'status');
    expect($reservation->refresh()->status)->toBe($arrived ? 'confirmed' : 'cancelled');
    if ($arrived) {
        Mail::assertNothingSent();
    } else {
        Mail::assertSent(ReservationCancellationMail::class, 1);
    }
    $this->assertDatabaseCount('payments', 0);
})->with([false, true]);

test('order completion notification is discarded on outer rollback', function () {
    Mail::fake();
    $order = Order::factory()->create(['status' => 'ready', 'total' => 40]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 40]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    DB::beginTransaction();
    $this->patch(route('admin.orders.update-status', $order), ['status' => 'completed', 'expected_status' => 'ready'])->assertSessionHas('status');
    Mail::assertNothingSent();
    DB::rollBack();
    expect($order->refresh()->status)->toBe('ready');
    Mail::assertNothingSent();
    $this->patch(route('admin.orders.update-status', $order), ['status' => 'completed', 'expected_status' => 'ready'])->assertSessionHas('status');
    Mail::assertSent(GoogleReviewInvitationMail::class, 1);
});

test('cancellation mail failure does not undo a committed cancellation', function () {
    $reservation = transitionReservation(['status' => 'confirmed']);
    Queue::fake();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP secret should not be logged'));
    Log::shouldReceive('warning')->once()->with('Reservation cancellation email send failed.', Mockery::on(
        fn (array $context): bool => $context === [
            'reservation_id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'exception_class' => RuntimeException::class,
        ],
    ));
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'cancelled', 'expected_status' => 'confirmed'])->assertSessionHas('status');
    expect($reservation->refresh()->status)->toBe('cancelled');
    Queue::assertNothingPushed();
});

test('callback payment is retained but ambiguous financial records cannot confirm the parent', function (string $kind) {
    Mail::fake();
    $parent = $kind === 'orders' ? Order::factory()->create(['total' => 40]) : transitionReservation(['reservation_type' => 'table_and_food']);
    if ($kind === 'orders') {
        $payment = Payment::factory()->create(['order_id' => $parent->id, 'amount' => 40]);
        Payment::factory()->create(['order_id' => $parent->id, 'amount' => 40]);
    } else {
        $payment = Payment::factory()->forReservation($parent)->create();
        Payment::factory()->forReservation($parent)->create();
    }
    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
    expect($payment->refresh()->status)->toBe('paid');
    expect($parent->refresh()->status)->toBe('pending');
    Mail::assertNothingSent();
})->with(['orders', 'reservations']);

test('manual payment matching preserves the callback amount tolerance', function (float $amount, bool $eligible) {
    Mail::fake();
    $order = Order::factory()->create(['total' => 40]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => $amount]);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.orders.update-status', $order), ['status' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas($eligible ? 'status' : 'error');
    expect($order->refresh()->status)->toBe($eligible ? 'confirmed' : 'pending');
    if ($eligible) {
        Mail::assertSent(OrderConfirmationMail::class, 1);
    } else {
        Mail::assertNothingSent();
    }
})->with([[40.00, true], [40.01, true], [40.02, false]]);

test('level two financial validation also protects arrival and completion', function (string $action, string $problem) {
    $this->travelTo('2026-10-09 03:00:00');
    Mail::fake();
    $reservation = transitionReservation([
        'reservation_type' => 'table_and_food',
        'status' => $action === 'confirmed' ? 'pending' : 'confirmed',
        'checked_in_at' => $action === 'completed' ? now()->subMinute() : null,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->paid()->create();
    match ($problem) {
        'pending', 'failed', 'refunded' => $payment->update(['status' => $problem]),
        'amount' => $payment->update(['amount' => 39]),
        'currency' => $payment->update(['currency' => 'USD']),
        'timestamp' => $payment->update(['paid_at' => null]),
        'wrong parent' => $payment->update(['reservation_id' => transitionReservation()->id]),
        'ambiguous' => Payment::factory()->forReservation($reservation)->paid()->create(),
    };
    $before = $reservation->getAttributes();
    $paymentBefore = $payment->refresh()->getAttributes();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    if ($action === 'check-in') {
        $this->post(route('admin.reservations.check-in'), ['reservation_number' => $reservation->reservation_number])->assertSessionHasErrors('reservation_number');
    } else {
        $this->patch(route('admin.reservations.update-status', $reservation), ['status' => $action, 'expected_status' => $reservation->status])->assertSessionHas('error');
    }
    expect($reservation->refresh()->getAttributes())->toBe($before);
    expect($payment->refresh()->getAttributes())->toBe($paymentBefore);
    Mail::assertNothingSent();
})->with(['confirmed', 'check-in', 'completed'])->with(['pending', 'failed', 'refunded', 'amount', 'currency', 'timestamp', 'wrong parent', 'ambiguous']);

test('manual allocation respects table suitability holds maintenance and half open intervals', function (string $condition, bool $allowed) {
    $this->travelTo('2026-10-09 02:00:00');
    Mail::fake();
    $reservation = transitionReservation(['created_at' => now()->subHour()]);
    if ($condition === 'maintenance') {
        $reservation->table->update(['status' => 'maintenance']);
    } elseif ($condition === 'minimum') {
        $reservation->table->update(['capacity' => 6]);
    } elseif ($condition === 'maximum') {
        $reservation->update(['guest_count' => 5]);
    } else {
        transitionReservation([
            'table_id' => $reservation->table_id,
            'status' => $condition === 'adjacent' ? 'completed' : 'pending',
            'reservation_time' => $condition === 'adjacent' ? '13:00' : '11:30',
            'created_at' => $condition === 'expired hold' ? now()->subMinutes(16) : now(),
        ]);
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'confirmed', 'expected_status' => 'pending'])
        ->assertSessionHas($allowed ? 'status' : 'error');
    expect($reservation->refresh()->status)->toBe($allowed ? 'confirmed' : 'pending');
    if ($allowed) {
        Mail::assertSent(ReservationConfirmationMail::class, 1);
    } else {
        Mail::assertNothingSent();
    }
})->with([['maintenance', false], ['minimum', false], ['maximum', false], ['live hold', false], ['expired hold', true], ['adjacent', true]]);

test('a callback winning after route binding makes manual confirmation stale without duplicate mail', function (string $kind) {
    $this->travelTo('2026-10-09 02:00:00');
    Mail::fake();
    $parent = $kind === 'orders' ? Order::factory()->create(['total' => 40]) : transitionReservation(['reservation_type' => 'table_and_food']);
    $payment = $kind === 'orders' ? Payment::factory()->create(['order_id' => $parent->id, 'amount' => 40]) : Payment::factory()->forReservation($parent)->create();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $interleaved = false;
    DB::listen(function ($query) use (&$interleaved, $kind, $payment) {
        if (! $interleaved && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from '.DB::connection()->getQueryGrammar()->wrapTable($kind))) {
            $interleaved = true;
            app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
        }
    });
    $this->patch(route('admin.'.$kind.'.update-status', $parent), ['status' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('error');
    expect($interleaved)->toBeTrue();
    expect($parent->refresh()->status)->toBe('confirmed');
    expect($payment->refresh()->status)->toBe('paid');
    Mail::assertSent($kind === 'orders' ? OrderConfirmationMail::class : ReservationConfirmationMail::class, 1);
})->with(['orders', 'reservations']);

test('manual confirmation followed by callback cannot initiate confirmation twice', function (string $kind) {
    Mail::fake();
    $parent = $kind === 'orders' ? Order::factory()->create(['total' => 40]) : transitionReservation(['reservation_type' => 'table_and_food']);
    $payment = $kind === 'orders' ? Payment::factory()->paid()->create(['order_id' => $parent->id, 'amount' => 40]) : Payment::factory()->forReservation($parent)->paid()->create();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.'.$kind.'.update-status', $parent), ['status' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('status');
    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
    Mail::assertSent($kind === 'orders' ? OrderConfirmationMail::class : ReservationConfirmationMail::class, 1);
    expect($parent->refresh()->status)->toBe('confirmed');
})->with(['orders', 'reservations']);

test('cancellation winning before payment preserves both cancellation and verified money', function (string $kind) {
    Mail::fake();
    $parent = $kind === 'orders' ? Order::factory()->create(['total' => 40]) : transitionReservation(['reservation_type' => 'table_and_food']);
    $payment = $kind === 'orders' ? Payment::factory()->create(['order_id' => $parent->id, 'amount' => 40]) : Payment::factory()->forReservation($parent)->create();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patch(route('admin.'.$kind.'.update-status', $parent), ['status' => 'cancelled', 'expected_status' => 'pending'])->assertSessionHas('status');
    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
    expect($parent->refresh()->status)->toBe('cancelled');
    expect($payment->refresh()->status)->toBe('paid');
    Mail::assertNotSent(OrderConfirmationMail::class);
    Mail::assertNotSent(ReservationConfirmationMail::class);
    if ($kind === 'reservations') {
        Mail::assertSent(ReservationCancellationMail::class, 1);
    }
})->with(['orders', 'reservations']);

test('a stale route bound order cannot overwrite a winning operational transition', function () {
    Mail::fake();
    $order = Order::factory()->create(['status' => 'confirmed', 'total' => 40]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 40]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $interleaved = false;
    DB::listen(function ($query) use (&$interleaved, $order) {
        if (! $interleaved && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from '.DB::connection()->getQueryGrammar()->wrapTable('orders'))) {
            $interleaved = true;
            app(OrderService::class)->transition($order->id, 'preparing', 'confirmed');
        }
    });
    $this->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled', 'expected_status' => 'confirmed'])->assertSessionHas('error');
    expect($interleaved)->toBeTrue();
    expect($order->refresh()->status)->toBe('preparing');
    Mail::assertNothingSent();
});

test('competing cancellation and completion have only one order transition owner', function (string $first) {
    Mail::fake();
    $order = Order::factory()->create(['status' => 'ready', 'total' => 40]);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 40]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patch(route('admin.orders.update-status', $order), ['status' => $first, 'expected_status' => 'ready'])->assertSessionHas('status');
    foreach (['completed', 'cancelled'] as $target) {
        $this->patch(route('admin.orders.update-status', $order), ['status' => $target, 'expected_status' => 'ready'])->assertSessionHas('error');
    }
    expect($order->refresh()->status)->toBe($first);
    if ($first === 'completed') {
        Mail::assertSent(GoogleReviewInvitationMail::class, 1);
    } else {
        Mail::assertNothingSent();
    }
})->with(['completed', 'cancelled']);

test('callback confirmation obeys the outermost transaction boundary', function (string $kind, bool $commit) {
    Mail::fake();
    $parent = $kind === 'orders' ? Order::factory()->create(['total' => 40]) : transitionReservation(['reservation_type' => 'table_and_food']);
    $payment = $kind === 'orders' ? Payment::factory()->create(['order_id' => $parent->id, 'amount' => 40]) : Payment::factory()->forReservation($parent)->create();
    DB::beginTransaction();
    app(MockPaymentService::class)->processDemoOutcome($payment, 'success');
    Mail::assertNothingSent();
    if ($commit) {
        DB::commit();
        Mail::assertSent($kind === 'orders' ? OrderConfirmationMail::class : ReservationConfirmationMail::class, 1);
    } else {
        DB::rollBack();
        expect($payment->refresh()->status)->toBe('pending');
        expect($parent->refresh()->status)->toBe('pending');
        Mail::assertNothingSent();
    }
})->with(['orders', 'reservations'])->with([false, true]);

test('admin and customer cancellation notifications respect outer rollback', function (bool $customer, bool $commit) {
    $this->travelTo('2026-10-09 02:00:00');
    Mail::fake();
    $reservation = transitionReservation(['status' => 'confirmed']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    DB::beginTransaction();
    if ($customer) {
        $this->post(URL::temporarySignedRoute('reservation.cancel', now()->addHour(), ['reservation' => $reservation]))->assertOk();
    } else {
        $this->patch(route('admin.reservations.update-status', $reservation), ['status' => 'cancelled', 'expected_status' => 'confirmed'])->assertSessionHas('status');
    }
    Mail::assertNothingSent();
    if ($commit) {
        DB::commit();
        Mail::assertSent(ReservationCancellationMail::class, 1);
    } else {
        DB::rollBack();
        expect($reservation->refresh()->status)->toBe('confirmed');
        Mail::assertNothingSent();
    }
})->with([false, true])->with([false, true]);

test('admin status forms include their expected source state', function (string $kind) {
    $parent = $kind === 'orders' ? Order::factory()->create() : transitionReservation();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.'.$kind.'.show', $parent))
        ->assertOk()->assertSee('name="expected_status" value="pending"', false);
})->with(['orders', 'reservations']);

test('expected status never replaces administrator authorization', function (string $kind, bool $authenticated) {
    Mail::fake();
    $parent = $kind === 'orders' ? Order::factory()->create() : transitionReservation();
    if ($authenticated) {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
    }
    $response = $this->patch(route('admin.'.$kind.'.update-status', $parent), ['status' => 'cancelled', 'expected_status' => 'pending']);
    if ($authenticated) {
        $response->assertForbidden();
    } else {
        $response->assertRedirect(route('admin.login'));
    }
    expect($parent->refresh()->status)->toBe('pending');
    Mail::assertNothingSent();
})->with(['orders', 'reservations'])->with([false, true]);
