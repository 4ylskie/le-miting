<?php

use App\Mail\OrderConfirmationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\Payments\MockPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function receiptEmailPaidOrder(): Order
{
    $order = Order::factory()->create(['total' => 19.50]);
    $item = MenuItem::factory()->create();
    $order->orderItems()->create([
        'menu_item_id' => $item->id,
        'item_name' => 'Snapshot Laksa',
        'unit_price' => 19.50,
        'quantity' => 1,
    ]);
    Payment::factory()->paid()->create([
        'order_id' => $order->id,
        'amount' => 19.50,
        'provider_reference' => 'PAY-2026-ORDER-RECEIPT',
    ]);

    return $order->fresh(['orderItems', 'payment']);
}

function receiptEmailPaidReservation(): Reservation
{
    $reservation = Reservation::factory()->withFood()->confirmed()->create();
    $item = MenuItem::factory()->create();
    $reservation->reservationFoods()->create([
        'menu_item_id' => $item->id,
        'item_name' => 'Snapshot Satay',
        'unit_price' => 12.50,
        'quantity' => 2,
    ]);
    Payment::factory()->forReservation($reservation)->paid()->create([
        'provider_reference' => 'PAY-2026-RESERVATION-RECEIPT',
    ]);

    return $reservation->fresh(['reservationFoods', 'payment', 'table']);
}

function receiptEmailSignature(Payment $payment, string $amount, string $status): string
{
    $callbackPayment = $payment->replicate();
    $callbackPayment->provider_reference = $payment->provider_reference;
    $callbackPayment->amount = $amount;

    return app(MockPaymentService::class)
        ->callbackPayload($callbackPayment, $status === 'paid' ? 'success' : 'failure')['signature'];
}

test('a paid order confirmation includes a signed receipt link and receipt PDF attachment', function () {
    config(['app.url' => 'http://localhost']);
    $order = receiptEmailPaidOrder();
    $mail = new OrderConfirmationMail($order);

    $mail->assertSeeInHtml('/orders/'.$order->id.'/receipt?');
    expect($mail->rawAttachments)->toHaveCount(1);
    expect($mail->rawAttachments[0]['name'])->toBe('receipt-'.$order->order_number.'.pdf');
    expect($mail->rawAttachments[0]['options'])->toBe(['mime' => 'application/pdf']);
    expect($mail->rawAttachments[0]['data'])->toStartWith('%PDF');
});

test('a paid table and food reservation confirmation includes a signed receipt link and receipt PDF attachment', function () {
    config(['app.url' => 'http://localhost']);
    $reservation = receiptEmailPaidReservation();
    $mail = new ReservationConfirmationMail($reservation);

    $mail->assertSeeInHtml($reservation->reservation_number);
    $mail->assertSeeInHtml('/reservation/'.$reservation->id.'/receipt?');
    expect($mail->rawAttachments)->toHaveCount(1);
    expect($mail->rawAttachments[0]['name'])->toBe('receipt-'.$reservation->reservation_number.'.pdf');
    expect($mail->rawAttachments[0]['options'])->toBe(['mime' => 'application/pdf']);
    expect($mail->rawAttachments[0]['data'])->toStartWith('%PDF');
});

test('a table only reservation confirmation has no food receipt attachment', function () {
    config(['app.url' => 'http://localhost']);
    $reservation = Reservation::factory()->confirmed()->create();
    $reservation->load('reservationFoods', 'payment', 'table');
    $mail = new ReservationConfirmationMail($reservation);

    $mail->assertSeeInHtml($reservation->reservation_number);
    $mail->assertDontSeeInHtml('/receipt?');
    $mail->assertHasNoAttachments();
});

test('receipt templates use stored line item snapshots after menu data changes', function () {
    $order = receiptEmailPaidOrder();
    $menuItem = $order->orderItems->first()->menuItem;
    $menuItem->update(['name' => 'Current Menu Name', 'price' => 99.99]);

    $html = view('pdfs.receipt', ['order' => $order->fresh(['orderItems', 'payment'])])->render();

    expect($html)
        ->toContain('Snapshot Laksa')
        ->toContain('RM 19.50')
        ->not->toContain('Current Menu Name')
        ->not->toContain('RM 99.99');
});

test('order receipt uses standard payment wording for each gateway', function () {
    $order = receiptEmailPaidOrder();
    config()->set('restaurant.email', 'internal@example.test');

    foreach (['mock', 'fiuu'] as $driver) {
        config()->set('payment.driver', $driver);
        $receipt = view('pdfs.receipt', ['order' => $order])->render();

        expect($receipt)->toContain('Payment:</strong> Verified')
            ->toContain('Total paid')
            ->toContain($order->customer_email)
            ->not->toContain('lemiting@lemiting.com')
            ->not->toContain('internal@example.test')
            ->not->toContain('Simulated')
            ->not->toContain('Paid via FIUU');
    }
});
test('reservation food receipt uses standard payment wording for each gateway', function () {
    $reservation = receiptEmailPaidReservation();
    config()->set('restaurant.email', 'internal@example.test');

    foreach (['mock', 'fiuu'] as $driver) {
        config()->set('payment.driver', $driver);
        $receipt = view('pdfs.reservation-receipt', ['reservation' => $reservation])->render();

        expect($receipt)->toContain('Payment:</strong> Verified')
            ->toContain('Total paid')
            ->toContain($reservation->customer_email)
            ->not->toContain('lemiting@lemiting.com')
            ->not->toContain('internal@example.test')
            ->not->toContain('Simulated')
            ->not->toContain('Paid via FIUU');
    }
});
test('an expired signed order receipt link renders the invalid link page', function () {
    config(['app.url' => 'http://localhost']);
    $order = receiptEmailPaidOrder();
    $url = URL::temporarySignedRoute('orders.receipt', now()->subHour(), ['order' => $order->id]);

    $this->get($url)
        ->assertStatus(403)
        ->assertSee('This link is no longer valid.');
});

test('a mail transport failure after payment verification keeps the order paid and confirmed', function () {
    config()->set('payment.driver', 'mock');
    config()->set('payment.fiuu.secret_key', 'test-secret');
    $order = Order::factory()->create(['status' => 'pending', 'total' => 20.00]);
    $payment = Payment::factory()->create(['order_id' => $order->id, 'amount' => 20.00]);
    $amount = '20.00';
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

    $this->post(route('payment.callback'), [
        'reference' => $payment->provider_reference,
        'amount' => $amount,
        'status' => 'paid',
        'currency' => 'MYR',
        'signature' => receiptEmailSignature($payment, $amount, 'paid'),
    ])->assertRedirect();

    expect($payment->refresh()->status)->toBe('paid');
    expect($order->refresh()->status)->toBe('confirmed');
});

test('a mail transport failure after reservation payment verification keeps the reservation paid and confirmed', function () {
    config()->set('payment.driver', 'mock');
    config()->set('payment.fiuu.secret_key', 'test-secret');
    $reservation = Reservation::factory()->withFood()->create([
        'status' => 'pending',
        'table_id' => Table::factory()->create(['capacity' => 4])->id,
        'guest_count' => 2,
    ]);
    $item = MenuItem::factory()->create();
    $reservation->reservationFoods()->create([
        'menu_item_id' => $item->id,
        'item_name' => 'Satay',
        'unit_price' => 10.00,
        'quantity' => 1,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->create(['amount' => 10.00]);
    $amount = '10.00';
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

    $this->post(route('payment.callback'), [
        'reference' => $payment->provider_reference,
        'amount' => $amount,
        'status' => 'paid',
        'currency' => 'MYR',
        'signature' => receiptEmailSignature($payment, $amount, 'paid'),
    ])->assertRedirect();

    expect($payment->refresh()->status)->toBe('paid');
    expect($reservation->refresh()->status)->toBe('confirmed');
});

test('a table only reservation remains confirmed when its confirmation email fails', function () {
    $table = Table::factory()->create(['status' => 'available', 'capacity' => 4]);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

    $this->post(route('reservation.store'), [
        'reservation_type' => 'table_only',
        'table_id' => $table->id,
        'reservation_date' => now()->addDay()->format('Y-m-d'),
        'reservation_time' => '11:00',
        'guest_count' => 2,
        'customer_name' => 'Aida Rahman',
        'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789',
    ])->assertRedirect();

    expect(Reservation::firstOrFail()->status)->toBe('confirmed');
});

test('a Google Review mail transport failure does not undo an order completion', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create(['status' => 'ready']);
    Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => $order->total]);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

    $this->actingAs($admin)
        ->patch(route('admin.orders.update-status', $order), ['status' => 'completed', 'expected_status' => $order->status])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe('completed');
});

test('a Google Review mail transport failure does not undo a reservation completion', function () {
    $this->travelTo('2026-10-09 04:00:00');
    $admin = User::factory()->create(['role' => 'admin']);
    $reservation = Reservation::factory()->confirmed()->create([
        'table_id' => Table::factory()->create(['capacity' => 4])->id,
        'guest_count' => 2,
        'reservation_date' => '2026-10-09',
        'reservation_time' => '11:00:00',
        'checked_in_at' => now()->subHour(),
    ]);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP unavailable'));

    $this->actingAs($admin)
        ->patch(route('admin.reservations.update-status', $reservation), ['status' => 'completed', 'expected_status' => $reservation->status])
        ->assertRedirect();

    expect($reservation->refresh()->status)->toBe('completed');
});
